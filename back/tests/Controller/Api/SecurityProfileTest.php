<?php

namespace App\Tests\Controller\Api;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityProfileTest extends ApiTestCase
{
    public function testAuthenticatedUserCanReadOnlyTheirSafeProfile(): void
    {
        $token = $this->createTestUser();
        $this->client->request('GET', '/api/account/me', server: ['HTTP_X_AUTH_TOKEN' => $token]);

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('client@example.test', $body['data']['email']);
        self::assertSame(['ROLE_USER'], $body['data']['roles']);
        self::assertArrayNotHasKey('motDePasseHash', $body['data']);
        self::assertArrayNotHasKey('apiTokenHash', $body['data']);
    }

    public function testProfileReadRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/account/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testRegistrationHashesPasswordAndReturnsOneTimeApiToken(): void
    {
        $headers = $this->registrationHeaders();
        $this->client->request(
            'POST',
            '/api/registration',
            server: $headers,
            content: json_encode([
                'prenom' => 'Camille',
                'nom' => 'Martin',
                'email' => 'camille@example.test',
                'password' => 'MotDePasse-123!',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(201);
        $body = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('camille@example.test', $body['data']['email']);
        self::assertSame(['ROLE_USER'], $body['data']['roles']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $body['apiToken']);
        self::assertArrayNotHasKey('password', $body['data']);
        self::assertArrayNotHasKey('motDePasseHash', $body['data']);

        $utilisateur = $this->entityManager->getRepository(Utilisateur::class)->findOneBy([
            'email' => 'camille@example.test',
        ]);
        self::assertInstanceOf(Utilisateur::class, $utilisateur);
        self::assertNotSame('MotDePasse-123!', $utilisateur->getMotDePasseHash());
        self::assertSame(hash('sha256', $body['apiToken']), $utilisateur->getApiTokenHash());
        self::assertNotSame($body['apiToken'], $utilisateur->getApiTokenHash());
    }

    public function testRegistrationRejectsInvalidPayloadAndDuplicateEmail(): void
    {
        $headers = $this->registrationHeaders();
        $this->client->request(
            'POST',
            '/api/registration',
            server: $headers,
            content: '{invalid-json'
        );
        self::assertResponseStatusCodeSame(400);

        $this->client->request(
            'POST',
            '/api/registration',
            server: $headers,
            content: json_encode([
                'prenom' => 'Camille',
                'nom' => 'Martin',
                'email' => 'not-an-email',
                'password' => 'secret',
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(400);

        $payload = json_encode([
            'prenom' => 'Camille',
            'nom' => 'Martin',
            'email' => 'duplicate@example.test',
            'password' => 'MotDePasse-123!',
        ], JSON_THROW_ON_ERROR);
        $this->client->request('POST', '/api/registration', server: $headers, content: $payload);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('POST', '/api/registration', server: $headers, content: $payload);
        self::assertResponseStatusCodeSame(409);
    }

    public function testRegistrationRateLimiterRejectsTheSixthAttemptFromSameIp(): void
    {
        $headers = $this->registrationHeaders();

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->client->request('POST', '/api/registration', server: $headers, content: '{invalid-json');
            self::assertResponseStatusCodeSame(400);
        }

        $this->client->request('POST', '/api/registration', server: $headers, content: '{invalid-json');
        self::assertResponseStatusCodeSame(429);
        self::assertGreaterThan(0, (int) $this->client->getResponse()->headers->get('Retry-After'));
    }

    public function testLoginRejectsWrongPasswordAndRotatesTheApiToken(): void
    {
        $oldToken = $this->createTestUser();

        $this->client->request(
            'POST',
            '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'client@example.test',
                'password' => 'wrong-password',
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(401);

        $this->client->request(
            'POST',
            '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'client@example.test',
                'password' => 'Mot-De-Passe-Initial-123!',
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $newToken = $body['apiToken'];
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $newToken);
        self::assertNotSame($oldToken, $newToken);

        $this->client->request('GET', '/api/account/me', server: ['HTTP_X_AUTH_TOKEN' => $oldToken]);
        self::assertResponseStatusCodeSame(401);
        $this->client->request('GET', '/api/account/me', server: ['HTTP_X_AUTH_TOKEN' => $newToken]);
        self::assertResponseIsSuccessful();
    }

    public function testUserCanUpdateTheirProfileAndPassword(): void
    {
        $token = $this->createTestUser();
        $originalHash = $this->findUser('client@example.test')->getMotDePasseHash();
        $this->client->request(
            'PUT',
            '/api/account/me',
            server: [
                'HTTP_X_AUTH_TOKEN' => $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'prenom' => 'Camille',
                'nom' => 'Martin',
                'nbConvivesDefaut' => 3,
                'allergies' => 'Noix',
                'password' => 'Nouveau-Mot-De-Passe-123!',
                'currentPassword' => 'Mot-De-Passe-Initial-123!',
                'roles' => ['ROLE_ADMIN'],
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Camille', $body['data']['prenom']);
        self::assertSame('Martin', $body['data']['nom']);
        self::assertSame(3, $body['data']['nbConvivesDefaut']);
        self::assertSame('Noix', $body['data']['allergies']);
        self::assertSame(['ROLE_USER'], $body['data']['roles']);
        self::assertArrayNotHasKey('password', $body['data']);
        self::assertArrayNotHasKey('motDePasseHash', $body['data']);

        $this->entityManager->clear();
        $utilisateur = $this->findUser('client@example.test');
        self::assertNotSame($originalHash, $utilisateur->getMotDePasseHash());
        self::assertNotNull($utilisateur->getDateModification());
        self::assertTrue(
            static::getContainer()->get(UserPasswordHasherInterface::class)
                ->isPasswordValid($utilisateur, 'Nouveau-Mot-De-Passe-123!')
        );
    }

    public function testInvalidProfileUpdateDoesNotChangeTheAccount(): void
    {
        $token = $this->createTestUser();
        $this->client->request(
            'PUT',
            '/api/account/me',
            server: [
                'HTTP_X_AUTH_TOKEN' => $token,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'prenom' => '   ',
                'nom' => 'Martin',
                'nbConvivesDefaut' => 2,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(400);
        $this->entityManager->clear();
        self::assertSame('Alice', $this->findUser('client@example.test')->getPrenom());
    }

    public function testWeakPasswordAndOversizedNamesAreRejectedAtRegistration(): void
    {
        $headers = $this->registrationHeaders();
        $body = ['prenom' => 'Alice', 'nom' => 'Martin', 'email' => 'weak@example.test', 'password' => 'abc'];
        $this->client->request('POST', '/api/registration', server: $headers, content: json_encode($body, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);
        $body['password'] = 'Mot-De-Passe-123!';
        $body['prenom'] = str_repeat('A', 81);
        $this->client->request('POST', '/api/registration', server: $headers, content: json_encode($body, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);
    }

    public function testChangingPasswordRequiresCurrentPasswordAndRevokesToken(): void
    {
        $token = $this->createTestUser();
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $token];
        $body = ['prenom' => 'Alice', 'nom' => 'Martin', 'password' => 'Nouveau-Mot-De-Passe-123!', 'currentPassword' => 'wrong'];
        $this->client->request('PUT', '/api/account/me', server: $headers, content: json_encode($body, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);
        $body['currentPassword'] = 'Mot-De-Passe-Initial-123!';
        $this->client->request('PUT', '/api/account/me', server: $headers, content: json_encode($body, JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/api/account/me', server: ['HTTP_X_AUTH_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testLogoutRevokesToken(): void
    {
        $token = $this->createTestUser();
        $this->client->request('POST', '/api/logout', server: ['HTTP_X_AUTH_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', '/api/account/me', server: ['HTTP_X_AUTH_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testProfileEmailMustBeValidAndUnique(): void
    {
        $token = $this->createTestUser();
        $this->createTestUser('other@example.test', ['ROLE_USER'], 'other-token');
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $token];
        $body = ['prenom' => 'Alice', 'nom' => 'Martin', 'email' => 'other@example.test', 'currentPassword' => 'Mot-De-Passe-Initial-123!'];
        $this->client->request('PUT', '/api/account/me', server: $headers, content: json_encode($body, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(409);
        $body['email'] = 'nouveau@example.test';
        $this->client->request('PUT', '/api/account/me', server: $headers, content: json_encode($body, JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        self::assertSame('nouveau@example.test', json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']['email']);
    }

    public function testChangingEmailRequiresTheCurrentPassword(): void
    {
        $token = $this->createTestUser();
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $token];
        $payload = ['prenom' => 'Alice', 'nom' => 'Martin', 'email' => 'nouvelle-adresse@example.test'];

        $this->client->request('PUT', '/api/account/me', server: $headers, content: json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);
        self::assertSame('client@example.test', $this->findUser('client@example.test')->getEmail());

        $payload['currentPassword'] = 'Mot-De-Passe-Incorrect-123!';
        $this->client->request('PUT', '/api/account/me', server: $headers, content: json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);
        self::assertSame('client@example.test', $this->findUser('client@example.test')->getEmail());

        $payload['currentPassword'] = 'Mot-De-Passe-Initial-123!';
        $this->client->request('PUT', '/api/account/me', server: $headers, content: json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        self::assertSame('nouvelle-adresse@example.test', $this->findUser('nouvelle-adresse@example.test')->getEmail());
    }

    public function testLoginThrottlingBlocksRepeatedFailedAttempts(): void
    {
        $email = 'throttle-'.bin2hex(random_bytes(6)).'@example.test';
        $this->createTestUser($email);
        $address = '2001:db8::'.bin2hex(random_bytes(2));
        $headers = ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => $address];
        for ($i = 0; $i < 5; ++$i) {
            $this->client->request('POST', '/api/login', server: $headers,
                content: json_encode(['email' => $email, 'password' => 'incorrect'], JSON_THROW_ON_ERROR));
            self::assertResponseStatusCodeSame(401);
        }
        $this->client->request('POST', '/api/login', server: $headers,
            content: json_encode(['email' => $email, 'password' => 'Mot-De-Passe-Initial-123!'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(401);
        $body = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('apiToken', $body);
    }

    public function testCardIsPublicButOnlyAdministratorsCanChangeIt(): void
    {
        $clientToken = $this->createTestUser();
        $adminToken = $this->createTestUser('admin@example.test', ['ROLE_ADMIN'], 'admin-api-token');
        $this->client->request('GET', '/api/categories');
        self::assertResponseIsSuccessful();

        $this->client->request(
            'POST',
            '/api/categories',
            server: [
                'HTTP_X_AUTH_TOKEN' => $clientToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['titre' => 'Entrées'], JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertJsonStringEqualsJsonString(
            '{"error":"Accès interdit."}',
            $this->client->getResponse()->getContent()
        );

        $this->client->request(
            'POST',
            '/api/categories',
            server: [
                'HTTP_X_AUTH_TOKEN' => $adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['titre' => 'Entrées'], JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(201);
    }

    private function findUser(string $email): Utilisateur
    {
        $utilisateur = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(Utilisateur::class, $utilisateur);

        return $utilisateur;
    }

    /** @return array{CONTENT_TYPE: string, REMOTE_ADDR: string} */
    private function registrationHeaders(): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '2001:db8::'.bin2hex(random_bytes(8)),
        ];
    }
}
