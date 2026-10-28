<?php

namespace App\Controller\Api;

use App\Entity\Restaurant;
use App\Entity\Reservation;
use App\Repository\RestaurantRepository;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RestaurantSettingsController extends AbstractController
{
    private const DAYS = ['mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    #[OA\Get(path: '/api/restaurant', summary: 'Consulter les horaires et la capacité', security: [], responses: [new OA\Response(response: 200, description: 'Restaurant.'), new OA\Response(response: 404, description: 'Restaurant non configuré.')])]
    #[Route('/api/restaurant', name: 'api_restaurant_show', methods: ['GET'])]
    public function show(RestaurantRepository $restaurants): JsonResponse
    {
        $restaurant = $restaurants->findOneBy([], ['id' => 'ASC']);
        return $restaurant ? $this->response($restaurant) : $this->json(['error' => 'Restaurant non configuré.'], 404);
    }

    #[OA\Put(path: '/api/restaurant', summary: 'Modifier les services et la capacité', description: 'Services de deux heures, même jour, du mardi au dimanche.', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['horaire_midi', 'horaire_soir', 'capacite_max'], properties: [
        new OA\Property(property: 'horaire_midi', required: ['debut', 'fin'], properties: [new OA\Property(property: 'debut', type: 'string', example: '12:00'), new OA\Property(property: 'fin', type: 'string', example: '14:00')], type: 'object'),
        new OA\Property(property: 'horaire_soir', required: ['debut', 'fin'], properties: [new OA\Property(property: 'debut', type: 'string', example: '19:00'), new OA\Property(property: 'fin', type: 'string', example: '21:00')], type: 'object'),
        new OA\Property(property: 'capacite_max', type: 'integer', minimum: 1, maximum: 65535),
    ])), responses: [new OA\Response(response: 200, description: 'Paramètres enregistrés.'), new OA\Response(response: 400, description: 'Données invalides.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Administrateur requis.'), new OA\Response(response: 404, description: 'Restaurant non configuré.'), new OA\Response(response: 409, description: 'Des réservations futures seraient affectées.')])]
    #[Route('/api/restaurant', name: 'api_restaurant_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(Request $request, RestaurantRepository $restaurants, EntityManagerInterface $em, ReservationService $reservationService): JsonResponse
    {
        $restaurant = $restaurants->findOneBy([], ['id' => 'ASC']);
        if (!$restaurant) return $this->json(['error' => 'Restaurant non configuré.'], 404);
        try { $input = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR); } catch (\JsonException) { $input = null; }
        if (!is_object($input) || array_diff(array_keys(get_object_vars($input)), ['horaire_midi', 'horaire_soir', 'capacite_max'])
            || !isset($input->horaire_midi, $input->horaire_soir, $input->capacite_max)
            || !is_int($input->capacite_max) || $input->capacite_max < 1 || $input->capacite_max > 65535
            || !$this->validService($input->horaire_midi) || !$this->validService($input->horaire_soir)
            || $input->horaire_midi->fin >= $input->horaire_soir->debut) {
            return $this->json(['error' => 'Horaires ou capacité invalides.'], 400);
        }
        $midday = ['jours' => self::DAYS, 'debut' => $input->horaire_midi->debut, 'fin' => $input->horaire_midi->fin];
        $evening = ['jours' => self::DAYS, 'debut' => $input->horaire_soir->debut, 'fin' => $input->horaire_soir->fin];
        $candidate = (new Restaurant())->setHoraireMidi($midday)->setHoraireSoir($evening)->setCapaciteMax($input->capacite_max);
        $em->getConnection()->beginTransaction();
        try {
            $reservationService->lockRestaurant($restaurant);
            $future = $em->getRepository(Reservation::class)->createQueryBuilder('r')
                ->where('r.restaurant = :restaurant AND r.dateReservation >= :today AND r.statut <> :cancelled')
                ->setParameter('restaurant', $restaurant)
                ->setParameter('today', new \DateTime('today', new \DateTimeZone('Europe/Paris')), \Doctrine\DBAL\Types\Types::DATE_MUTABLE)
                ->setParameter('cancelled', 'annulee')->getQuery()->getResult();
            $counts = [];
            foreach ($future as $reservation) {
                $date = $reservation->getDateReservation();
                $time = $reservation->getHeurePrevue();
                if (!$date || !$time || !$reservationService->isFuture($date, $time)) continue;
                $slot = $reservationService->serviceFor($candidate, $date, $time);
                if (!$slot) {
                    $em->getConnection()->rollBack();
                    return $this->json(['error' => 'Une réservation future ne correspondrait plus aux horaires.'], 409);
                }
                $key = $date->format('Y-m-d').' '.$slot['debut'];
                $counts[$key] = ($counts[$key] ?? 0) + $reservation->getNbConvives();
                if ($counts[$key] > $input->capacite_max) {
                    $em->getConnection()->rollBack();
                    return $this->json(['error' => 'La capacité serait inférieure aux réservations existantes.'], 409);
                }
            }
            $restaurant->setHoraireMidi($midday)->setHoraireSoir($evening)
                ->setCapaciteMax($input->capacite_max)->setDateModification(new \DateTimeImmutable());
            $em->flush();
            $em->getConnection()->commit();
            return $this->response($restaurant);
        } catch (\Throwable $error) {
            if ($em->getConnection()->isTransactionActive()) $em->getConnection()->rollBack();
            throw $error;
        }
    }

    private function validService(mixed $service): bool
    {
        if (!is_object($service) || count(get_object_vars($service)) !== 2 || !isset($service->debut, $service->fin)
            || !is_string($service->debut) || !is_string($service->fin)
            || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $service->debut)
            || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $service->fin)) return false;
        [$h1, $m1] = array_map('intval', explode(':', $service->debut));
        [$h2, $m2] = array_map('intval', explode(':', $service->fin));
        return $h2 * 60 + $m2 - $h1 * 60 - $m1 === 120;
    }

    private function response(Restaurant $restaurant): JsonResponse
    {
        return $this->json(['data' => ['uuid' => $restaurant->getUuid(), 'nom' => $restaurant->getNom(), 'description' => $restaurant->getDescription(), 'horaire_midi' => $restaurant->getHoraireMidi(), 'horaire_soir' => $restaurant->getHoraireSoir(), 'capacite_max' => $restaurant->getCapaciteMax()]]);
    }
}
