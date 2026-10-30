<?php

namespace App\Tests\Controller\Api;

use App\Entity\Restaurant;
use App\Util\UuidGenerator;

final class ReservationApiTest extends ApiTestCase
{
    private function setupRestaurant(): void
    {
        $restaurant = (new Restaurant())->setUuid(UuidGenerator::generate())->setNom('Quai Antique')
            ->setHoraireMidi(['debut' => '12:00', 'fin' => '14:00'])
            ->setHoraireSoir(['debut' => '19:00', 'fin' => '21:00'])->setCapaciteMax(4);
        $this->entityManager->persist($restaurant);
        $this->entityManager->flush();
    }

    public function testAvailabilityAndReservationRespectServiceCapacity(): void
    {
        $this->setupRestaurant();
        $token = $this->createTestUser();
        $date = (new \DateTimeImmutable('next tuesday', new \DateTimeZone('Europe/Paris')))->format('Y-m-d');
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $token];
        $payload = ['date' => $date, 'heure' => '12:00', 'nb_convives' => 3, 'allergies' => 'Noix'];
        $this->client->request('POST', '/api/reservations', server: $headers, content: json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $this->client->request('GET', '/api/availability?date='.$date.'&guests=2');
        self::assertResponseIsSuccessful();
        $slots = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data'];
        self::assertSame(['heure' => '12:00', 'places_restantes' => 1, 'disponible' => false], $slots[0]);
        self::assertSame('14:00', $slots[8]['heure']);
        $payload['heure'] = '13:45';
        $payload['nb_convives'] = 2;
        $this->client->request('POST', '/api/reservations', server: $headers, content: json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(409);
        $payload['heure'] = '19:00';
        $this->client->request('POST', '/api/reservations', server: $headers, content: json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
    }

    public function testOwnershipAndAdminAccessAreEnforced(): void
    {
        $this->setupRestaurant();
        $owner = $this->createTestUser();
        $other = $this->createTestUser('other@example.test', ['ROLE_USER'], 'other-token');
        $admin = $this->createTestUser('admin@example.test', ['ROLE_ADMIN'], 'admin-token');
        $date = (new \DateTimeImmutable('next tuesday', new \DateTimeZone('Europe/Paris')))->format('Y-m-d');
        $payload = ['date' => $date, 'heure' => '12:00', 'nb_convives' => 2];
        $this->client->request('POST', '/api/reservations', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $owner], content: json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $uuid = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']['uuid'];
        $this->client->request('GET', '/api/reservations', server: ['HTTP_X_AUTH_TOKEN' => $other]);
        self::assertSame([], json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']);
        $this->client->request('GET', '/api/reservations?date='.$date, server: ['HTTP_X_AUTH_TOKEN' => $admin]);
        self::assertResponseIsSuccessful();
        self::assertSame('client@example.test', json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data'][0]['client']['email']);
        $this->client->request('GET', '/api/reservations/'.$uuid, server: ['HTTP_X_AUTH_TOKEN' => $other]);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('PUT', '/api/reservations/'.$uuid, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $other], content: json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(404);
        $this->client->request('DELETE', '/api/reservations/'.$uuid, server: ['HTTP_X_AUTH_TOKEN' => $other]);
        self::assertResponseStatusCodeSame(404);
        $payload['nb_convives'] = 4;
        $this->client->request('PUT', '/api/reservations/'.$uuid, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $admin], content: json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $this->client->request('DELETE', '/api/reservations/'.$uuid, server: ['HTTP_X_AUTH_TOKEN' => $admin]);
        self::assertResponseStatusCodeSame(204);
    }

    public function testClosedDayAndInvalidQuarterHourAreRejected(): void
    {
        $this->setupRestaurant();
        $token = $this->createTestUser();
        $monday = (new \DateTimeImmutable('next monday', new \DateTimeZone('Europe/Paris')))->format('Y-m-d');
        $this->client->request('GET', '/api/availability?date='.$monday.'&guests=1');
        self::assertSame([], json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']);
        $date = (new \DateTimeImmutable('next tuesday', new \DateTimeZone('Europe/Paris')))->format('Y-m-d');
        $this->client->request('POST', '/api/reservations', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $token],
            content: json_encode(['date' => $date, 'heure' => '12:07', 'nb_convives' => 2], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);
        $pastDate = (new \DateTimeImmutable('yesterday', new \DateTimeZone('Europe/Paris')))->format('Y-m-d');
        $this->client->request('POST', '/api/reservations', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $token],
            content: json_encode(['date' => $pastDate, 'heure' => '12:00', 'nb_convives' => 2], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/api/reservations', server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['date' => $date, 'heure' => '12:00', 'nb_convives' => 2], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(401);
    }

    public function testDeletingAccountRequiresPasswordAndDeletesItsReservations(): void
    {
        $this->setupRestaurant();
        $token = $this->createTestUser();
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $token];
        $date = (new \DateTimeImmutable('next tuesday', new \DateTimeZone('Europe/Paris')))->format('Y-m-d');
        $this->client->request('POST', '/api/reservations', server: $headers,
            content: json_encode(['date' => $date, 'heure' => '12:00', 'nb_convives' => 2], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $this->client->request('DELETE', '/api/account/me', server: $headers, content: '{"currentPassword":"wrong"}');
        self::assertResponseStatusCodeSame(400);
        $this->client->request('DELETE', '/api/account/me', server: $headers, content: '{"currentPassword":"Mot-De-Passe-Initial-123!"}');
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', '/api/account/me', server: ['HTTP_X_AUTH_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(401);
        self::assertSame(0, $this->entityManager->getRepository(\App\Entity\Reservation::class)->count([]));
    }

    public function testSettingsCannotInvalidateExistingReservations(): void
    {
        $this->setupRestaurant();
        $client = $this->createTestUser();
        $admin = $this->createTestUser('admin@example.test', ['ROLE_ADMIN'], 'admin-token');
        $date = (new \DateTimeImmutable('next tuesday', new \DateTimeZone('Europe/Paris')))->format('Y-m-d');
        $this->client->request('POST', '/api/reservations', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $client],
            content: json_encode(['date' => $date, 'heure' => '12:00', 'nb_convives' => 3], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $body = ['horaire_midi' => ['debut' => '12:00', 'fin' => '14:00'], 'horaire_soir' => ['debut' => '19:00', 'fin' => '21:00'], 'capacite_max' => 2];
        $this->client->request('PUT', '/api/restaurant', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $admin], content: json_encode($body, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(409);
        $body['capacite_max'] = 4;
        $body['horaire_midi'] = ['debut' => '13:00', 'fin' => '15:00'];
        $this->client->request('PUT', '/api/restaurant', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $admin], content: json_encode($body, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(409);
        $this->client->request('GET', '/api/restaurant');
        self::assertSame(4, json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']['capacite_max']);
    }
}
