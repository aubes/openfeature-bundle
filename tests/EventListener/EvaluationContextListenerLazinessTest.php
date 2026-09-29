<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\Tests\EventListener;

use Aubes\OpenFeatureBundle\EvaluationContext\EvaluationContextProviderInterface;
use Aubes\OpenFeatureBundle\EvaluationContext\LazyEvaluationContext;
use Aubes\OpenFeatureBundle\Event\EvaluationContextContributedEvent;
use Aubes\OpenFeatureBundle\EventListener\EvaluationContextListener;
use OpenFeature\implementation\flags\MutableAttributes;
use OpenFeature\implementation\flags\MutableEvaluationContext;
use OpenFeature\interfaces\flags\EvaluationContext;
use OpenFeature\OpenFeatureAPI;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(EvaluationContextListener::class)]
class EvaluationContextListenerLazinessTest extends TestCase
{
    public function testDoesNotRunProvidersOnKernelRequest(): void
    {
        $provider = $this->createMock(EvaluationContextProviderInterface::class);
        $provider->expects($this->never())->method('getContext');

        $api = new OpenFeatureAPI();
        (new EvaluationContextListener($api, [$provider]))->onKernelRequest($this->makeEvent());

        $this->assertInstanceOf(LazyEvaluationContext::class, $api->getEvaluationContext());
    }

    public function testIgnoresSubRequests(): void
    {
        $api = new OpenFeatureAPI();
        $listener = new EvaluationContextListener($api, [$this->providerReturning(new MutableEvaluationContext('user-1'))]);
        $listener->onKernelRequest($this->makeEvent(HttpKernelInterface::SUB_REQUEST));

        $this->assertNotInstanceOf(LazyEvaluationContext::class, $api->getEvaluationContext());
    }

    public function testRunsProvidersOnceOnFirstRead(): void
    {
        $provider = $this->createMock(EvaluationContextProviderInterface::class);
        $provider->expects($this->once())->method('getContext')->willReturn(new MutableEvaluationContext('user-1'));

        $api = new OpenFeatureAPI();
        (new EvaluationContextListener($api, [$provider]))->onKernelRequest($this->makeEvent());

        $context = $api->getEvaluationContext();
        $this->assertNotNull($context);
        $this->assertSame('user-1', $context->getTargetingKey());
        $this->assertSame('user-1', $context->getTargetingKey());
    }

    public function testMergesContextsFromMultipleProviders(): void
    {
        $api = new OpenFeatureAPI();
        $listener = new EvaluationContextListener($api, [
            $this->providerReturning(new MutableEvaluationContext('user-1', new MutableAttributes(['plan' => 'free', 'region' => 'eu']))),
            $this->providerReturning(null),
            $this->providerReturning(new MutableEvaluationContext('user-2', new MutableAttributes(['plan' => 'premium']))),
        ]);
        $listener->onKernelRequest($this->makeEvent());

        $context = $api->getEvaluationContext();
        $this->assertNotNull($context);
        $this->assertSame('user-2', $context->getTargetingKey());
        $this->assertSame(['plan' => 'premium', 'region' => 'eu'], $context->getAttributes()->toArray());
    }

    public function testKeepsPreviousContextWhenNoProviderContributes(): void
    {
        $api = new OpenFeatureAPI();
        $api->setEvaluationContext(new MutableEvaluationContext('boot'));

        (new EvaluationContextListener($api, [$this->providerReturning(null)]))->onKernelRequest($this->makeEvent());

        $this->assertSame('boot', $api->getEvaluationContext()?->getTargetingKey());
    }

    public function testFallsBackToAnEmptyContextWhenNothingContributes(): void
    {
        $api = new OpenFeatureAPI();
        (new EvaluationContextListener($api, [$this->providerReturning(null)]))->onKernelRequest($this->makeEvent());

        $context = $api->getEvaluationContext();
        $this->assertNotNull($context);
        $this->assertNull($context->getTargetingKey());
        $this->assertSame([], $context->getAttributes()->toArray());
    }

    public function testDispatchesContributedEventsOnResolution(): void
    {
        /** @var \ArrayObject<int, object> $dispatched */
        $dispatched = new \ArrayObject();
        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static function (object $event) use ($dispatched): object {
            $dispatched->append($event);

            return $event;
        });

        $api = new OpenFeatureAPI();
        $listener = new EvaluationContextListener($api, [$this->providerReturning(new MutableEvaluationContext('user-1'))], $dispatcher);
        $listener->onKernelRequest($this->makeEvent());

        $this->assertCount(0, $dispatched);

        $api->getEvaluationContext()?->getTargetingKey();

        $this->assertCount(1, $dispatched);
        $this->assertInstanceOf(EvaluationContextContributedEvent::class, $dispatched[0]);
    }

    public function testLogsAndSkipsFailingProvider(): void
    {
        $failing = $this->createStub(EvaluationContextProviderInterface::class);
        $failing->method('getContext')->willThrowException(new \RuntimeException('boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->anything(), $this->callback(static fn (array $context): bool => $context['message'] === 'boom'));

        $api = new OpenFeatureAPI();
        $listener = new EvaluationContextListener($api, [$failing, $this->providerReturning(new MutableEvaluationContext('user-1'))], null, $logger);
        $listener->onKernelRequest($this->makeEvent());

        $this->assertSame('user-1', $api->getEvaluationContext()?->getTargetingKey());
    }

    public function testLogsAFailingContributedEventListener(): void
    {
        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willThrowException(new \RuntimeException('boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->anything(), $this->callback(static fn (array $context): bool => $context['message'] === 'boom'));

        $api = new OpenFeatureAPI();
        $listener = new EvaluationContextListener($api, [$this->providerReturning(new MutableEvaluationContext('user-1'))], $dispatcher, $logger);
        $listener->onKernelRequest($this->makeEvent());

        $this->assertSame('user-1', $api->getEvaluationContext()?->getTargetingKey());
    }

    public function testResetDropsPendingContextWithoutResolvingIt(): void
    {
        $provider = $this->createMock(EvaluationContextProviderInterface::class);
        $provider->expects($this->never())->method('getContext');

        $api = new OpenFeatureAPI();
        $listener = new EvaluationContextListener($api, [$provider]);
        $listener->onKernelRequest($this->makeEvent());
        $listener->reset();

        $context = $api->getEvaluationContext();
        $this->assertNotInstanceOf(LazyEvaluationContext::class, $context);
        $this->assertNull($context?->getTargetingKey());
    }

    private function makeEvent(int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create('/'), $requestType);
    }

    private function providerReturning(?EvaluationContext $context): EvaluationContextProviderInterface
    {
        $provider = $this->createStub(EvaluationContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        return $provider;
    }
}
