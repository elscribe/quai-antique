<?php

namespace App\Security;

use App\Repository\UtilisateurRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final class ApiTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly UtilisateurRepository $utilisateurRepository)
    {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-AUTH-TOKEN');
    }

    public function authenticate(Request $request): Passport
    {
        $apiToken = $request->headers->get('X-AUTH-TOKEN');

        if (!is_string($apiToken) || $apiToken === '') {
            throw new CustomUserMessageAuthenticationException('Jeton API manquant.');
        }

        $utilisateur = $this->utilisateurRepository->findOneBy([
            'apiTokenHash' => hash('sha256', $apiToken),
        ]);

        if ($utilisateur === null) {
            throw new CustomUserMessageAuthenticationException('Jeton API invalide.');
        }

        return new SelfValidatingPassport(
            new UserBadge($utilisateur->getUserIdentifier(), static fn () => $utilisateur)
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?Response {
        return null;
    }

    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception
    ): ?Response {
        return new JsonResponse(
            ['error' => 'Jeton API invalide.'],
            Response::HTTP_UNAUTHORIZED
        );
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(
            ['error' => 'Authentification requise. Fournissez un jeton API valide.'],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
