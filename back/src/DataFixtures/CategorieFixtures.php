<?php

namespace App\DataFixtures;

use App\Entity\Categorie;
use App\Util\UuidGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

final class CategorieFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        foreach ([
            ['Entrées', 'entrees'],
            ['Plats', 'plats'],
            ['Fromages', 'fromages'],
            ['Desserts', 'desserts'],
            ['À partager · minimum deux personnes', 'partager'],
        ] as [$titre, $reference]) {
            $categorie = (new Categorie())
                ->setUuid(UuidGenerator::generate())
                ->setTitre($titre);

            $manager->persist($categorie);
            $this->addReference('categorie.'.$reference, $categorie);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['quai-antique-demo', 'catalogue'];
    }
}
