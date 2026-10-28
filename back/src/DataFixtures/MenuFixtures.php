<?php

namespace App\DataFixtures;

use App\Entity\Categorie;
use App\Entity\Menu;
use App\Entity\MenuCategorie;
use App\Entity\Restaurant;
use App\Util\UuidGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class MenuFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $restaurant = $this->getReference(RestaurantFixtures::REFERENCE, Restaurant::class);
        $menus = [
            ['Découverte', 'Entrée + plat ou plat + dessert selon la sélection du jour.', '29.00', ['entrees', 'plats']],
            ['Terroirs', 'Entrée, plat et dessert ou sélection de fromages.', '39.00', ['entrees', 'plats', 'desserts']],
            ['Voyage Savoyard', 'Parcours savoyard en cinq services pour toute la table.', '89.00', ['entrees', 'plats', 'fromages', 'desserts']],
        ];

        foreach ($menus as [$titre, $description, $prix, $categoryReferences]) {
            $menu = (new Menu())
                ->setUuid(UuidGenerator::generate())
                ->setTitre($titre)
                ->setDescription($description)
                ->setPrix($prix)
                ->setRestaurant($restaurant);
            $manager->persist($menu);

            foreach ($categoryReferences as $categoryReference) {
                $categorie = $this->getReference(
                    'categorie.'.$categoryReference,
                    Categorie::class
                );
                $menuCategorie = (new MenuCategorie())
                    ->setMenu($menu)
                    ->setCategorie($categorie);
                $manager->persist($menuCategorie);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [RestaurantFixtures::class, CategorieFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['quai-antique-demo', 'catalogue'];
    }
}
