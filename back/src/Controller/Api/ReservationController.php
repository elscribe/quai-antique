<?php

namespace App\Controller\Api;

use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Repository\ReservationRepository;
use App\Repository\RestaurantRepository;
use App\Service\ReservationService;
use App\Util\UuidGenerator;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ReservationController extends AbstractController
{
    #[OA\Get(path: '/api/availability', summary: 'Consulter les créneaux disponibles', security: [], parameters: [
        new OA\Parameter(name: 'date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        new OA\Parameter(name: 'guests', in: 'query', required: true, schema: new OA\Schema(type: 'integer', minimum: 1)),
    ], responses: [
        new OA\Response(response: 200, description: 'Créneaux et nombre de places restantes.'),
        new OA\Response(response: 400, description: 'Date ou nombre de convives invalide.'),
        new OA\Response(response: 404, description: 'Restaurant non configuré.'),
    ])]
    #[Route('/api/availability', name: 'api_availability', methods: ['GET'])]
    public function availability(Request $request, RestaurantRepository $restaurants, ReservationService $service): JsonResponse
    {
        $dateValue = $request->query->get('date');
        $guestsValue = $request->query->get('guests');
        $date = is_string($dateValue) ? $service->parseDate($dateValue) : null;
        if (!$date || !is_string($guestsValue) || !ctype_digit($guestsValue) || (int) $guestsValue < 1 || (int) $guestsValue > 65535) {
            return $this->json(['error' => 'Date ou nombre de convives invalide.'], 400);
        }
        $restaurant = $restaurants->findOneBy([], ['id' => 'ASC']);
        if (!$restaurant) {
            return $this->json(['error' => 'Restaurant non configuré.'], 404);
        }
        return $this->json(['data' => $service->slots($restaurant, $date, (int) $guestsValue)]);
    }

    #[OA\Get(path: '/api/reservations', summary: 'Lister ses réservations ou toutes les réservations pour un administrateur', parameters: [
        new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
    ], responses: [
        new OA\Response(response: 200, description: 'Réservations accessibles au compte.'),
        new OA\Response(response: 400, description: 'Date invalide.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
    ])]
    #[Route('/api/reservations', name: 'api_reservations_index', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] Utilisateur $user, ReservationRepository $reservations, ReservationService $service): JsonResponse
    {
        $criteria = $this->isGranted('ROLE_ADMIN') ? [] : ['utilisateur' => $user];
        $dateValue = $request->query->get('date');
        if ($dateValue !== null) {
            $date = is_string($dateValue) ? $service->parseDate($dateValue) : null;
            if (!$date) {
                return $this->json(['error' => 'Date invalide.'], 400);
            }
            $criteria['dateReservation'] = $date;
        }
        $items = $reservations->findBy($criteria, ['dateReservation' => 'ASC', 'heurePrevue' => 'ASC']);
        return $this->json(['data' => array_map($this->represent(...), $items)]);
    }

    #[OA\Post(path: '/api/reservations', summary: 'Réserver une table pour le compte connecté', requestBody: new OA\RequestBody(
        required: true, content: new OA\JsonContent(required: ['date', 'heure', 'nb_convives'], properties: [
            new OA\Property(property: 'date', type: 'string', format: 'date', example: '2027-05-04'),
            new OA\Property(property: 'heure', type: 'string', example: '12:15'),
            new OA\Property(property: 'nb_convives', type: 'integer', example: 2),
            new OA\Property(property: 'allergies', type: 'string', nullable: true),
        ])
    ), responses: [
        new OA\Response(response: 201, description: 'Réservation créée.'),
        new OA\Response(response: 400, description: 'Réservation invalide.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 409, description: 'Places insuffisantes.'),
    ])]
    #[Route('/api/reservations', name: 'api_reservations_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] Utilisateur $user, RestaurantRepository $restaurants, ReservationService $service, EntityManagerInterface $em, UrlGeneratorInterface $urls): JsonResponse
    {
        $restaurant = $restaurants->findOneBy([], ['id' => 'ASC']);
        if (!$restaurant) {
            return $this->json(['error' => 'Restaurant non configuré.'], 404);
        }
        $input = $this->readInput($request, $service);
        if ($input instanceof JsonResponse) {
            return $input;
        }
        if (!$service->isFuture($input['date'], $input['time'])) {
            return $this->json(['error' => 'Ce créneau ne peut pas être réservé.'], 400);
        }
        $em->getConnection()->beginTransaction();
        try {
            $service->lockRestaurant($restaurant);
            $slot = $service->serviceFor($restaurant, $input['date'], $input['time']);
            if (!$slot) {
                $em->getConnection()->rollBack();
                return $this->json(['error' => 'Ce créneau ne peut pas être réservé.'], 400);
            }
            if ($service->remaining($restaurant, $input['date'], $slot) < $input['guests']) {
                $em->getConnection()->rollBack();
                return $this->json(['error' => 'Places insuffisantes pour ce service.'], 409);
            }
            $reservation = (new Reservation())->setUuid(UuidGenerator::generate())->setRestaurant($restaurant)
                ->setUtilisateur($user)->setStatut('confirmee');
            $this->apply($reservation, $input);
            $em->persist($reservation);
            $em->flush();
            $em->getConnection()->commit();
            return $this->json(['data' => $this->represent($reservation)], 201, [
                'Location' => $urls->generate('api_reservations_show', ['uuid' => $reservation->getUuid()], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
        } catch (\Throwable $error) {
            if ($em->getConnection()->isTransactionActive()) $em->getConnection()->rollBack();
            throw $error;
        }
    }

    #[OA\Get(path: '/api/reservations/{uuid}', summary: 'Consulter une réservation autorisée', parameters: [
        new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ], responses: [
        new OA\Response(response: 200, description: 'Réservation accessible.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 404, description: 'Réservation introuvable ou non accessible.'),
    ])]
    #[Route('/api/reservations/{uuid}', name: 'api_reservations_show', methods: ['GET'])]
    public function show(string $uuid, #[CurrentUser] Utilisateur $user, ReservationRepository $reservations): JsonResponse
    {
        $reservation = $reservations->findOneBy(['uuid' => $uuid]);
        if (!$this->accessible($reservation, $user)) {
            return $this->json(['error' => 'Réservation introuvable.'], 404);
        }
        return $this->json(['data' => $this->represent($reservation)]);
    }

    #[OA\Put(path: '/api/reservations/{uuid}', summary: 'Modifier une réservation autorisée', parameters: [
        new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['date', 'heure', 'nb_convives'], properties: [
            new OA\Property(property: 'date', type: 'string', format: 'date'),
            new OA\Property(property: 'heure', type: 'string', example: '12:15'),
            new OA\Property(property: 'nb_convives', type: 'integer'),
            new OA\Property(property: 'allergies', type: 'string', nullable: true),
        ])), responses: [
        new OA\Response(response: 200, description: 'Réservation modifiée.'),
        new OA\Response(response: 400, description: 'Données invalides.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 404, description: 'Réservation introuvable ou non accessible.'),
        new OA\Response(response: 409, description: 'Places insuffisantes.'),
    ])]
    #[Route('/api/reservations/{uuid}', name: 'api_reservations_update', methods: ['PUT'])]
    public function update(string $uuid, Request $request, #[CurrentUser] Utilisateur $user, ReservationRepository $reservations, ReservationService $service, EntityManagerInterface $em): JsonResponse
    {
        $reservation = $reservations->findOneBy(['uuid' => $uuid]);
        if (!$this->accessible($reservation, $user)) {
            return $this->json(['error' => 'Réservation introuvable.'], 404);
        }
        $input = $this->readInput($request, $service);
        if ($input instanceof JsonResponse) {
            return $input;
        }
        $restaurant = $reservation->getRestaurant();
        if (!$service->isFuture($input['date'], $input['time'])) {
            return $this->json(['error' => 'Ce créneau ne peut pas être réservé.'], 400);
        }
        $em->getConnection()->beginTransaction();
        try {
            $service->lockRestaurant($restaurant);
            $slot = $service->serviceFor($restaurant, $input['date'], $input['time']);
            if (!$slot) {
                $em->getConnection()->rollBack();
                return $this->json(['error' => 'Ce créneau ne peut pas être réservé.'], 400);
            }
            if ($service->remaining($restaurant, $input['date'], $slot, $reservation) < $input['guests']) {
                $em->getConnection()->rollBack();
                return $this->json(['error' => 'Places insuffisantes pour ce service.'], 409);
            }
            $this->apply($reservation, $input);
            $reservation->setDateModification(new \DateTimeImmutable());
            $em->flush();
            $em->getConnection()->commit();
            return $this->json(['data' => $this->represent($reservation)]);
        } catch (\Throwable $error) {
            if ($em->getConnection()->isTransactionActive()) $em->getConnection()->rollBack();
            throw $error;
        }
    }

    #[OA\Delete(path: '/api/reservations/{uuid}', summary: 'Supprimer une réservation autorisée', parameters: [
        new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ], responses: [
        new OA\Response(response: 204, description: 'Réservation supprimée.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 404, description: 'Réservation introuvable ou non accessible.'),
    ])]
    #[Route('/api/reservations/{uuid}', name: 'api_reservations_delete', methods: ['DELETE'])]
    public function delete(string $uuid, #[CurrentUser] Utilisateur $user, ReservationRepository $reservations, EntityManagerInterface $em): Response
    {
        $reservation = $reservations->findOneBy(['uuid' => $uuid]);
        if (!$this->accessible($reservation, $user)) {
            return $this->json(['error' => 'Réservation introuvable.'], 404);
        }
        $em->remove($reservation);
        $em->flush();
        return new Response(null, 204);
    }

    private function accessible(?Reservation $reservation, Utilisateur $user): bool
    {
        return $reservation !== null && ($this->isGranted('ROLE_ADMIN') || $reservation->getUtilisateur()?->getId() === $user->getId());
    }

    private function readInput(Request $request, ReservationService $service): array|JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'JSON invalide.'], 400);
        }
        if (!is_array($data) || !is_string($data['date'] ?? null) || !is_string($data['heure'] ?? null)
            || !is_int($data['nb_convives'] ?? null) || $data['nb_convives'] < 1 || $data['nb_convives'] > 65535
            || (isset($data['allergies']) && (!is_string($data['allergies']) || mb_strlen($data['allergies']) > 5000))) {
            return $this->json(['error' => 'Date, heure, nombre de convives ou allergies invalides.'], 400);
        }
        $date = $service->parseDate($data['date']);
        $time = $service->parseTime($data['heure']);
        if (!$date || !$time) {
            return $this->json(['error' => 'Date ou heure invalide.'], 400);
        }
        return ['date' => $date, 'time' => $time, 'guests' => $data['nb_convives'], 'allergies' => isset($data['allergies']) ? trim($data['allergies']) : null];
    }

    private function apply(Reservation $reservation, array $data): void
    {
        $reservation->setDateReservation($data['date'])->setHeurePrevue($data['time'])
            ->setNbConvives($data['guests'])->setAllergies($data['allergies']);
    }

    private function represent(Reservation $reservation): array
    {
        $data = ['uuid' => $reservation->getUuid(), 'date' => $reservation->getDateReservation()?->format('Y-m-d'),
            'heure' => $reservation->getHeurePrevue()?->format('H:i'), 'nb_convives' => $reservation->getNbConvives(),
            'allergies' => $reservation->getAllergies(), 'statut' => $reservation->getStatut()];
        if ($this->isGranted('ROLE_ADMIN')) {
            $user = $reservation->getUtilisateur();
            $data['client'] = ['prenom' => $user?->getPrenom(), 'nom' => $user?->getNom(), 'email' => $user?->getEmail()];
        }
        return $data;
    }
}
