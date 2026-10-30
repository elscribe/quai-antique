<?php

namespace App\Tests\Controller\Api;

use App\Entity\Restaurant;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class InitRestaurantCommandTest extends ApiTestCase
{
    public function testCreatesRestaurantUsingFixtureDefaults(): void
    {
        $tester = $this->commandTester();

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $restaurant = $this->entityManager->getRepository(Restaurant::class)->findOneBy([]);
        self::assertInstanceOf(Restaurant::class, $restaurant);
        self::assertSame('Quai Antique', $restaurant->getNom());
        self::assertSame('Restaurant gastronomique savoyard à Chambéry.', $restaurant->getDescription());
        self::assertSame([
            'jours' => ['mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'],
            'debut' => '12:00',
            'fin' => '14:00',
        ], $restaurant->getHoraireMidi());
        self::assertSame([
            'jours' => ['mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'],
            'debut' => '19:00',
            'fin' => '21:00',
        ], $restaurant->getHoraireSoir());
        self::assertSame(40, $restaurant->getCapaciteMax());
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i', (string) $restaurant->getUuid());
        self::assertInstanceOf(\DateTimeImmutable::class, $restaurant->getDateCreation());
        self::assertNull($restaurant->getDateModification());
    }

    public function testSecondExecutionSucceedsWithoutChangingExistingRestaurant(): void
    {
        $application = new Application(self::$kernel);
        $first = new CommandTester($application->find('app:init-restaurant'));
        self::assertSame(Command::SUCCESS, $first->execute([
            '--capacite-max' => '50',
            '--debut-midi' => '11:00',
            '--fin-midi' => '13:00',
            '--debut-soir' => '18:00',
            '--fin-soir' => '20:00',
        ]));
        $restaurant = $this->entityManager->getRepository(Restaurant::class)->findOneBy([]);
        self::assertInstanceOf(Restaurant::class, $restaurant);
        $originalUuid = $restaurant->getUuid();
        $originalCreationDate = $restaurant->getDateCreation();

        $second = new CommandTester($application->find('app:init-restaurant'));
        self::assertSame(Command::SUCCESS, $second->execute([
            '--capacite-max' => '25',
            '--debut-midi' => '12:00',
            '--fin-midi' => '14:00',
            '--debut-soir' => '19:00',
            '--fin-soir' => '21:00',
        ]));

        $this->entityManager->clear();
        $unchanged = $this->entityManager->getRepository(Restaurant::class)->findOneBy([]);
        self::assertInstanceOf(Restaurant::class, $unchanged);
        self::assertSame(1, $this->entityManager->getRepository(Restaurant::class)->count([]));
        self::assertSame($originalUuid, $unchanged->getUuid());
        self::assertSame($originalCreationDate->format(DATE_ATOM), $unchanged->getDateCreation()->format(DATE_ATOM));
        self::assertSame(50, $unchanged->getCapaciteMax());
        self::assertSame('11:00', $unchanged->getHoraireMidi()['debut']);
        self::assertStringContainsString('Aucune donnée n’a été modifiée', $second->getDisplay());
    }

    public function testRejectsInvalidCapacityOrScheduleWithoutCreatingRestaurant(): void
    {
        $invalidOptions = [
            ['--capacite-max' => '0'],
            ['--debut-midi' => '25:00'],
            [
                '--debut-midi' => '18:00',
                '--fin-midi' => '20:00',
                '--debut-soir' => '19:00',
                '--fin-soir' => '21:00',
            ],
        ];

        foreach ($invalidOptions as $options) {
            $tester = $this->commandTester();
            self::assertSame(Command::FAILURE, $tester->execute($options));
            self::assertStringContainsString('invalides', $tester->getDisplay());
            self::assertSame(0, $this->entityManager->getRepository(Restaurant::class)->count([]));
        }
    }

    private function commandTester(): CommandTester
    {
        $application = new Application(self::$kernel);

        return new CommandTester($application->find('app:init-restaurant'));
    }
}
