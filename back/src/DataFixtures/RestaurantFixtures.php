<?php

namespace App\DataFixtures;

use App\Entity\Restaurant;
use App\Util\UuidGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

final class RestaurantFixtures extends Fixture implements FixtureGroupInterface
{
    public const REFERENCE = 'restaurant.quai-antique';

    public function load(ObjectManager $manager): void
    {
        $existing = $manager->getRepository(Restaurant::class)->findOneBy([]);
        if ($existing !== null) {
            $this->addReference(self::REFERENCE, $existing);
            return;
        }
        $restaurant = (new Restaurant())
            ->setUuid(UuidGenerator::generate())
            ->setNom('Quai Antique')
            ->setDescription('Restaurant gastronomique savoyard à Chambéry.')
            ->setHoraireMidi([
                'jours' => ['mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'],
                'debut' => '12:00',
                'fin' => '14:00',
            ])
            ->setHoraireSoir([
                'jours' => ['mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'],
                'debut' => '19:00',
                'fin' => '21:00',
            ])
            ->setCapaciteMax(40);

        $manager->persist($restaurant);
        $this->addReference(self::REFERENCE, $restaurant);
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['quai-antique-demo', 'restaurant'];
    }
}
