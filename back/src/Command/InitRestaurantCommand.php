<?php

namespace App\Command;

use App\Entity\Restaurant;
use App\Repository\RestaurantRepository;
use App\Util\UuidGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:init-restaurant', description: 'Initialiser la configuration du restaurant si elle est absente.')]
final class InitRestaurantCommand extends Command
{
    private const DAYS = ['mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    public function __construct(
        private readonly RestaurantRepository $restaurants,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('capacite-max', null, InputOption::VALUE_OPTIONAL, 'Capacité maximale du restaurant', '40')
            ->addOption('debut-midi', null, InputOption::VALUE_OPTIONAL, 'Heure de début du service du midi', '12:00')
            ->addOption('fin-midi', null, InputOption::VALUE_OPTIONAL, 'Heure de fin du service du midi', '14:00')
            ->addOption('debut-soir', null, InputOption::VALUE_OPTIONAL, 'Heure de début du service du soir', '19:00')
            ->addOption('fin-soir', null, InputOption::VALUE_OPTIONAL, 'Heure de fin du service du soir', '21:00');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->restaurants->findOneBy([], ['id' => 'ASC']) !== null) {
            $io->success('Un restaurant est déjà configuré. Aucune donnée n’a été modifiée.');

            return Command::SUCCESS;
        }

        $capacityOption = (string) $input->getOption('capacite-max');
        $capacity = preg_match('/^\d+$/D', $capacityOption) === 1 ? (int) $capacityOption : null;
        $lunchStart = trim((string) $input->getOption('debut-midi'));
        $lunchEnd = trim((string) $input->getOption('fin-midi'));
        $dinnerStart = trim((string) $input->getOption('debut-soir'));
        $dinnerEnd = trim((string) $input->getOption('fin-soir'));

        $lunch = $this->service($lunchStart, $lunchEnd);
        $dinner = $this->service($dinnerStart, $dinnerEnd);

        if ($capacity === null || $capacity < 1 || $capacity > 65535
            || $lunch === null || $dinner === null
            || $lunchEnd >= $dinnerStart) {
            $io->error('Capacité ou horaires invalides. Chaque service doit durer deux heures, et le midi doit finir avant le soir.');

            return Command::FAILURE;
        }

        $restaurant = (new Restaurant())
            ->setUuid(UuidGenerator::generate())
            ->setNom('Quai Antique')
            ->setDescription('Restaurant gastronomique savoyard à Chambéry.')
            ->setHoraireMidi(['jours' => self::DAYS, 'debut' => $lunchStart, 'fin' => $lunchEnd])
            ->setHoraireSoir(['jours' => self::DAYS, 'debut' => $dinnerStart, 'fin' => $dinnerEnd])
            ->setCapaciteMax($capacity);

        $this->entityManager->persist($restaurant);
        $this->entityManager->flush();

        $io->success('Configuration initiale du restaurant créée.');

        return Command::SUCCESS;
    }

    /** @return array{start: int, end: int}|null */
    private function service(string $start, string $end): ?array
    {
        $startMinutes = $this->minutes($start);
        $endMinutes = $this->minutes($end);

        if ($startMinutes === null || $endMinutes === null || $endMinutes - $startMinutes !== 120) {
            return null;
        }

        return ['start' => $startMinutes, 'end' => $endMinutes];
    }

    private function minutes(string $time): ?int
    {
        if (preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time) !== 1) {
            return null;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
