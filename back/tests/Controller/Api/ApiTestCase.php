<?php

namespace App\Tests\Controller\Api;

use App\Entity\Categorie;
use App\Entity\Utilisateur;
use App\Util\UuidGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $connection = $this->entityManager->getConnection();
        $parameters = $connection->getParams();
        if (($parameters['driver'] ?? null) !== 'pdo_sqlite' || ($parameters['memory'] ?? false) !== true) {
            throw new LogicException('API functional tests refuse to reset a database other than SQLite in memory.');
        }

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        if ($connection->createSchemaManager()->listTableNames() !== []) {
            $schemaTool->dropSchema($metadata);
        }
        $schemaTool->createSchema($metadata);
    }

    protected function createTestUser(
        string $email = 'client@example.test',
        array $roles = ['ROLE_USER'],
        string $token = 'test-api-token'
    ): string {
        $utilisateur = (new Utilisateur())
            ->setUuid(UuidGenerator::generate())
            ->setPrenom('Alice')
            ->setNom('Martin')
            ->setEmail($email)
            ->setRoles($roles)
            ->setApiTokenHash(hash('sha256', $token))
            ->setApiTokenExpiresAt(new \DateTimeImmutable('+12 hours'));
        $utilisateur->setMotDePasseHash(
            static::getContainer()->get(UserPasswordHasherInterface::class)
                ->hashPassword($utilisateur, 'Mot-De-Passe-Initial-123!')
        );

        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        return $token;
    }

    protected function createTestCategory(string $titre = 'Entrées'): Categorie
    {
        $categorie = (new Categorie())
            ->setUuid(UuidGenerator::generate())
            ->setTitre($titre);
        $this->entityManager->persist($categorie);
        $this->entityManager->flush();

        return $categorie;
    }
}
