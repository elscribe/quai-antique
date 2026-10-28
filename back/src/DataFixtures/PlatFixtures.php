<?php

namespace App\DataFixtures;

use App\Entity\Categorie;
use App\Entity\Plat;
use App\Util\UuidGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class PlatFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $plats = [
            ['Velouté de courge des Bauges', 'Noisettes torréfiées et huile de noix.', '10.00', 'entrees'],
            ['Salade de l’alpage', 'Pommes de Savoie, noix, tomme et miel.', '12.00', 'entrees'],
            ['Rissoles salées', 'Noix, ricotta et salade d’herbes.', '14.00', 'entrees'],
            ['Diots de Savoie', 'Vin blanc, crozets au sarrasin et oignons confits.', '25.00', 'plats'],
            ['Pommes de terre des Aravis', 'Polenta crémeuse et légumes de saison.', '26.00', 'plats'],
            ['Farçon savoyard', 'Jus à la génépi et légumes rôtis.', '27.00', 'plats'],
            ['Sélection de Savoie', 'Beaufort, tomme des Bauges et confiture maison.', '14.00', 'fromages'],
            ['Faisselle fermière', 'Miel de montagne et noix.', '8.00', 'desserts'],
            ['Gâteau de Savoie', 'Myrtilles et crème légère.', '9.00', 'desserts'],
            ['Fondue 100 % Savoie', 'Beaufort, Emmental de Savoie, pain et salade. Prix par personne.', '28.00', 'partager'],
            ['Matouille de Tomme des Bauges', 'Grenaille, salade et charcuteries savoyardes. Prix par personne.', '26.00', 'partager'],
        ];

        foreach ($plats as [$titre, $description, $prix, $categorieReference]) {
            $categorie = $this->getReference(
                'categorie.'.$categorieReference,
                Categorie::class
            );
            $plat = (new Plat())
                ->setUuid(UuidGenerator::generate())
                ->setTitre($titre)
                ->setDescription($description)
                ->setPrix($prix)
                ->setCategorie($categorie);

            $manager->persist($plat);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CategorieFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['quai-antique-demo', 'catalogue'];
    }
}
