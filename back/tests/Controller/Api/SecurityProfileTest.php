<?php

namespace App\Tests\Controller\Api;

use App\Entity\Utilisateur;
use App\Util\UuidGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityProfileTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        if ($this->entityManager->getConnection()->createSchemaManager()->listTableNames() !== []) {
            $schemaTool->dropSchema($metadata);
        }
        $schemaTool->createSchema($metadata);
    }

    public function testAuthenticatedUserCanReadOnlyTheirSafeProfile(): void
    {
        $token = $this->createUser();
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

    public function testUserCanUpdateTheirProfileAndPassword(): void
    {
        $token = $this->createUser();
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
        $token = $this->createUser();
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

    public function testCardIsPublicButOnlyAdministratorsCanChangeIt(): void
    {
        $clientToken = $this->createUser();
        $adminToken = $this->createUser('admin@example.test', ['ROLE_ADMIN']);
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

    private function createUser(
        string $email = 'client@example.test',
        array $roles = ['ROLE_USER']
    ): string {
        $token = 'test-token-'.$email;
        $utilisateur = new Utilisateur();
        $utilisateur->setUuid(UuidGenerator::generate());
        $utilisateur->setPrenom('Alice');
        $utilisateur->setNom('Martin');
        $utilisateur->setEmail($email);
        $utilisateur->setRoles($roles);
        $utilisateur->setApiTokenHash(hash('sha256', $token));
        $utilisateur->setMotDePasseHash(
            static::getContainer()->get(UserPasswordHasherInterface::class)
                ->hashPassword($utilisateur, 'Mot-De-Passe-Initial-123!')
        );

        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        return $token;
    }

    private function findUser(string $email): Utilisateur
    {
        $utilisateur = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(Utilisateur::class, $utilisateur);

        return $utilisateur;
    }
}
