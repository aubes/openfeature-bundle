<?php

declare(strict_types=1);

namespace Aubes\OpenFeatureBundle\EvaluationContext;

use OpenFeature\implementation\flags\MutableEvaluationContext;
use OpenFeature\interfaces\flags\EvaluationContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserEvaluationContextProvider implements EvaluationContextProviderInterface
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage)
    {
    }

    public function getContext(Request $request): ?EvaluationContext
    {
        $token = $this->tokenStorage->getToken();

        if ($token === null) {
            return null;
        }

        $user = $token->getUser();

        if ($user === null) {
            return null;
        }

        return new MutableEvaluationContext($user->getUserIdentifier());
    }
}
