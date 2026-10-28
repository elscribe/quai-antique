<?php

namespace App\DataFixtures;

use App\Entity\Utilisateur;
use App\Util\UuidGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UtilisateurFixtures extends Fixture implements FixtureGroupInterface
{
    public const CLIENT_REFERENCE = 'utilisateur.client';

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $accounts = [
            [self::CLIENT_REFERENCE, 'client@quai-antique.test', 'Camille', 'Martin', ['ROLE_USER']],
            ['utilisateur.admin', 'admin@quai-antique.test', 'Alex', 'Bernard', ['ROLE_ADMIN']],
        ];

        foreach ($accounts as [$reference, $email, $prenom, $nom, $roles]) {
            $utilisateur = (new Utilisateur())
                ->setUuid(UuidGenerator::generate())
                ->setEmail($email)
                ->setPrenom($prenom)
                ->setNom($nom)
                ->setRoles($roles);
            $utilisateur->setMotDePasseHash(
                $this->passwordHasher->hashPassword($utilisateur, 'Demo-QuaiAntique-2026!')
            );

            $manager->persist($utilisateur);
            $this->addReference($reference, $utilisateur);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['quai-antique-demo', 'utilisateurs'];
    }
}
