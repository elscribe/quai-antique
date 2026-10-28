<?php

namespace App\Tests\Controller\Api;

use App\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateAdminCommandTest extends ApiTestCase
{
    public function testAdminCreationIsInteractiveAndDoesNotPrintThePassword(): void
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:create-admin'));
        $password = 'Admin-Secure-123!';
        $tester->setInputs([$password, $password]);
        $exit = $tester->execute(['--email' => 'staff@example.test', '--prenom' => 'Camille', '--nom' => 'Martin']);
        self::assertSame(0, $exit);
        self::assertStringNotContainsString($password, $tester->getDisplay());
        $user = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => 'staff@example.test']);
        self::assertInstanceOf(Utilisateur::class, $user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, $password));

        $duplicate = new CommandTester($application->find('app:create-admin'));
        self::assertSame(1, $duplicate->execute(['--email' => 'staff@example.test', '--prenom' => 'Autre', '--nom' => 'Compte']));
    }
}
