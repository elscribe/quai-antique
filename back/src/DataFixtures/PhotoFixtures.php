<?php

namespace App\DataFixtures;

use App\Entity\Photo;
use App\Entity\Restaurant;
use App\Util\UuidGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class PhotoFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $restaurant = $this->getReference(RestaurantFixtures::REFERENCE, Restaurant::class);

        foreach ([
            ['Rissoles salées', '/assets/figma/galerie-rissoles.png'],
            ['Féra du lac', '/assets/figma/galerie-fera.png'],
            ['Fondue savoyarde', '/assets/figma/galerie-fondue.png'],
            ['Fromages de Savoie', '/assets/figma/galerie-fromages.png'],
            ['Gâteau de Savoie', '/assets/figma/galerie-gateau.png'],
            ['Cocktail signature', '/assets/figma/galerie-cocktail.png'],
            ['Le geste du chef', '/assets/figma/galerie-geste-chef.png'],
            ['La salle', '/assets/figma/galerie-salle.png'],
            ['La terrasse', '/assets/figma/galerie-terrasse.png'],
        ] as [$titre, $path]) {
            $photo = (new Photo())
                ->setUuid(UuidGenerator::generate())
                ->setTitre($titre)
                ->setUrlImage($path)
                ->setRestaurant($restaurant);

            $manager->persist($photo);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [RestaurantFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['quai-antique-demo', 'galerie'];
    }
}
