<?php

namespace App\Tests\Security;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Security\ApiTokenAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class ApiTokenAuthenticatorTest extends TestCase
{
    public function testSupportsRequestsThatCarryTheApiTokenHeader(): void
    {
        $authenticator = new ApiTokenAuthenticator($this->createStub(UtilisateurRepository::class));

        self::assertTrue($authenticator->supports(new Request(server: ['HTTP_X_AUTH_TOKEN' => 'token-test'])));
        self::assertFalse($authenticator->supports(new Request()));
    }

    public function testValidTokenIsHashedBeforeTheRepositoryLookup(): void
    {
        $token = 'api-token-raw-value';
        $user = (new Utilisateur())
            ->setUuid('00000000-0000-4000-8000-000000000001')
            ->setPrenom('Alex')
            ->setNom('Bernard')
            ->setEmail('alex@example.test')
            ->setApiTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
        $repository = $this->createMock(UtilisateurRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['apiTokenHash' => hash('sha256', $token)])
            ->willReturn($user);
        $authenticator = new ApiTokenAuthenticator($repository);

        $passport = $authenticator->authenticate(new Request(server: ['HTTP_X_AUTH_TOKEN' => $token]));
        $badge = $passport->getBadge(UserBadge::class);

        self::assertInstanceOf(UserBadge::class, $badge);
        self::assertSame($user, $badge->getUser());
    }

    public function testUnknownTokenIsRejected(): void
    {
        $repository = $this->createStub(UtilisateurRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $authenticator = new ApiTokenAuthenticator($repository);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate(new Request(server: ['HTTP_X_AUTH_TOKEN' => 'unknown-token']));
    }

    public function testExpiredTokenIsRejected(): void
    {
        $user = (new Utilisateur())->setEmail('alex@example.test')
            ->setApiTokenExpiresAt(new \DateTimeImmutable('-1 minute'));
        $repository = $this->createStub(UtilisateurRepository::class);
        $repository->method('findOneBy')->willReturn($user);
        $this->expectException(CustomUserMessageAuthenticationException::class);
        (new ApiTokenAuthenticator($repository))->authenticate(new Request(server: ['HTTP_X_AUTH_TOKEN' => 'expired']));
    }

    public function testMissingTokenIsRejectedWithoutLookingUpAUser(): void
    {
        $repository = $this->createMock(UtilisateurRepository::class);
        $repository->expects(self::never())->method('findOneBy');
        $authenticator = new ApiTokenAuthenticator($repository);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate(new Request());
    }
}
