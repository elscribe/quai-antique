<?php

namespace App\DataFixtures;

use App\Entity\Reservation;
use App\Entity\Restaurant;
use App\Entity\Utilisateur;
use App\Util\UuidGenerator;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

final class ReservationFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $restaurant = $this->getReference(RestaurantFixtures::REFERENCE, Restaurant::class);
        $utilisateur = $this->getReference(UtilisateurFixtures::CLIENT_REFERENCE, Utilisateur::class);
        $faker = Factory::create('fr_FR');
        $faker->seed(20261026);

        $dateReservation = DateTime::createFromImmutable(new DateTimeImmutable('+7 days'));
        $dateReservation->setTime(0, 0);

        $reservation = (new Reservation())
            ->setUuid(UuidGenerator::generate())
            ->setDateReservation($dateReservation)
            ->setHeurePrevue(DateTime::createFromImmutable(new DateTimeImmutable('+7 days 19:30')))
            ->setNbConvives(2)
            ->setAllergies($faker->boolean(20) ? 'Noix' : null)
            ->setStatut('confirmee')
            ->setRestaurant($restaurant)
            ->setUtilisateur($utilisateur);

        $manager->persist($reservation);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [RestaurantFixtures::class, UtilisateurFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['quai-antique-demo', 'reservations'];
    }
}
