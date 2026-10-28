<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\Restaurant;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

final class ReservationService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function parseDate(string $value): ?\DateTime
    {
        $date = \DateTime::createFromFormat('!Y-m-d', $value, new \DateTimeZone('Europe/Paris'));
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    public function parseTime(string $value): ?\DateTime
    {
        $time = \DateTime::createFromFormat('!H:i', $value, new \DateTimeZone('Europe/Paris'));
        return $time && $time->format('H:i') === $value ? $time : null;
    }

    /** @return array{debut:string,fin:string}|null */
    public function serviceFor(Restaurant $restaurant, \DateTime $date, \DateTime $time): ?array
    {
        if ((int) $date->format('N') === 1) {
            return null;
        }
        foreach ([$restaurant->getHoraireMidi(), $restaurant->getHoraireSoir()] as $service) {
            $start = isset($service['debut']) && is_string($service['debut']) ? $this->parseTime($service['debut']) : null;
            $end = isset($service['fin']) && is_string($service['fin']) ? $this->parseTime($service['fin']) : null;
            if ($start === null || $end === null) {
                continue;
            }
            $minutes = ((int) $time->format('H')) * 60 + (int) $time->format('i');
            $startMinutes = ((int) $start->format('H')) * 60 + (int) $start->format('i');
            $endMinutes = ((int) $end->format('H')) * 60 + (int) $end->format('i');
            if ($minutes >= $startMinutes && $minutes <= $endMinutes && ($minutes - $startMinutes) % 15 === 0) {
                return ['debut' => $service['debut'], 'fin' => $service['fin']];
            }
        }
        return null;
    }

    public function isFuture(\DateTime $date, \DateTime $time): bool
    {
        $scheduled = new \DateTimeImmutable($date->format('Y-m-d').' '.$time->format('H:i'), new \DateTimeZone('Europe/Paris'));
        return $scheduled > new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
    }

    /** @param array{debut:string,fin:string} $service */
    public function remaining(Restaurant $restaurant, \DateTime $date, array $service, ?Reservation $exclude = null): int
    {
        $query = $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(r.nbConvives), 0)')
            ->from(Reservation::class, 'r')
            ->where('r.restaurant = :restaurant AND r.dateReservation = :date')
            ->andWhere('r.heurePrevue >= :start AND r.heurePrevue <= :end')
            ->andWhere('r.statut <> :cancelled')
            ->setParameter('restaurant', $restaurant)
            ->setParameter('date', $date, Types::DATE_MUTABLE)
            ->setParameter('start', $this->parseTime($service['debut']), Types::TIME_MUTABLE)
            ->setParameter('end', $this->parseTime($service['fin']), Types::TIME_MUTABLE)
            ->setParameter('cancelled', 'annulee');
        if ($exclude !== null) {
            $query->andWhere('r.id <> :id')->setParameter('id', $exclude->getId());
        }
        return max(0, (int) $restaurant->getCapaciteMax() - (int) $query->getQuery()->getSingleScalarResult());
    }

    public function lockRestaurant(Restaurant $restaurant): void
    {
        // Après l’attente du verrou, relire aussi les horaires et la capacité les plus récents.
        if ($this->em->getConnection()->getDatabasePlatform() instanceof SQLitePlatform) {
            $this->em->refresh($restaurant);
        } else {
            $this->em->refresh($restaurant, LockMode::PESSIMISTIC_WRITE);
        }
    }

    /** @return list<array{heure:string,places_restantes:int,disponible:bool}> */
    public function slots(Restaurant $restaurant, \DateTime $date, int $guests): array
    {
        if ((int) $date->format('N') === 1) {
            return [];
        }
        $result = [];
        foreach ([$restaurant->getHoraireMidi(), $restaurant->getHoraireSoir()] as $service) {
            $start = isset($service['debut']) && is_string($service['debut']) ? $this->parseTime($service['debut']) : null;
            $end = isset($service['fin']) && is_string($service['fin']) ? $this->parseTime($service['fin']) : null;
            if (!$start || !$end || $end < $start) {
                continue;
            }
            $range = ['debut' => $start->format('H:i'), 'fin' => $end->format('H:i')];
            $remaining = $this->remaining($restaurant, $date, $range);
            for ($time = clone $start; $time <= $end; $time->modify('+15 minutes')) {
                $result[] = ['heure' => $time->format('H:i'), 'places_restantes' => $remaining,
                    'disponible' => $remaining >= $guests && $this->isFuture($date, $time)];
            }
        }
        return $result;
    }
}
