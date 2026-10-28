<?php

namespace App\Tests\Controller\Api;

use App\Entity\Plat;
use App\Util\UuidGenerator;
use Symfony\Component\HttpFoundation\Response;

final class CatalogApiTest extends ApiTestCase
{
    public function testCategoryCrudReturnsExpectedStatusesAndLocation(): void
    {
        $adminToken = $this->createTestUser('admin@example.test', ['ROLE_ADMIN'], 'admin-token');
        $this->client->request(
            'POST',
            '/api/categories',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $adminToken],
            content: json_encode(['titre' => 'Entrées'], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $location = $this->client->getResponse()->headers->get('Location');
        self::assertNotEmpty($location);
        self::assertStringContainsString('/api/categories/', $location);
        $body = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Entrées', $body['data']['titre']);
        $uuid = $body['data']['uuid'];

        $path = (string) parse_url($location, PHP_URL_PATH);
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSame($uuid, json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']['uuid']);

        $this->client->request(
            'PUT',
            $path,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $adminToken],
            content: json_encode(['titre' => 'Entrées de saison'], JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();
        self::assertSame('Entrées de saison', json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']['titre']);

        $this->client->request('DELETE', $path, server: ['HTTP_X_AUTH_TOKEN' => $adminToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $this->client->getResponse()->getContent());

        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCategoryRejectsInvalidBodiesAndDeletingAUsedCategory(): void
    {
        $adminToken = $this->createTestUser('admin@example.test', ['ROLE_ADMIN'], 'admin-token');

        $this->client->request('POST', '/api/categories');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request(
            'POST',
            '/api/categories',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $adminToken],
            content: '{invalid-json'
        );
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $this->client->request(
            'POST',
            '/api/categories',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $adminToken],
            content: json_encode(['titre' => '   '], JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $clientToken = $this->createTestUser('client@example.test', ['ROLE_USER'], 'client-token');
        $this->client->request(
            'POST',
            '/api/categories',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $clientToken],
            content: json_encode(['titre' => 'Plats'], JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $categorie = $this->createTestCategory('Plats');
        $plat = (new Plat())
            ->setUuid(UuidGenerator::generate())
            ->setTitre('Plat lié')
            ->setDescription('Donnée utilisée pour vérifier la contrainte de suppression.')
            ->setPrix('20.00')
            ->setCategorie($categorie);
        $this->entityManager->persist($plat);
        $this->entityManager->flush();

        $this->client->request(
            'DELETE',
            '/api/categories/'.$categorie->getUuid(),
            server: ['HTTP_X_AUTH_TOKEN' => $adminToken]
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $this->client->request('GET', '/api/categories/'.UuidGenerator::generate());
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testPlatCrudReturnsExpectedStatusesLocationAndSerializedRelation(): void
    {
        $adminToken = $this->createTestUser('admin@example.test', ['ROLE_ADMIN'], 'admin-token');
        $categorie = $this->createTestCategory('Plats');
        $payload = [
            'titre' => 'Diots de Savoie',
            'description' => 'Crozets au sarrasin.',
            'prix' => 25,
            'categorie_uuid' => $categorie->getUuid(),
        ];

        $this->client->request(
            'POST',
            '/api/plats',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $adminToken],
            content: json_encode($payload, JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $location = $this->client->getResponse()->headers->get('Location');
        self::assertNotEmpty($location);
        $body = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Plats', $body['data']['categorie']['titre']);
        self::assertSame('25.00', $body['data']['prix']);
        $path = (string) parse_url($location, PHP_URL_PATH);

        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSame('Diots de Savoie', json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']['titre']);

        $payload['titre'] = 'Diots savoyards';
        $this->client->request(
            'PUT',
            $path,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $adminToken],
            content: json_encode($payload, JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();
        self::assertSame('Diots savoyards', json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']['titre']);

        $this->client->request('DELETE', $path, server: ['HTTP_X_AUTH_TOKEN' => $adminToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testPlatValidationAndAuthorizationFailuresReturnDocumentedCodes(): void
    {
        $adminToken = $this->createTestUser('admin@example.test', ['ROLE_ADMIN'], 'admin-token');
        $clientToken = $this->createTestUser('client@example.test', ['ROLE_USER'], 'client-token');
        $categorie = $this->createTestCategory('Plats');
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $adminToken];

        $this->client->request('POST', '/api/plats', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->request(
            'POST',
            '/api/plats',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $clientToken],
            content: json_encode(['titre' => 'Plat'], JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $payload = ['titre' => 'Plat', 'description' => 'Description', 'prix' => -1, 'categorie_uuid' => $categorie->getUuid()];
        $this->client->request('POST', '/api/plats', server: $headers, content: json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $payload['prix'] = 12.50;
        $payload['categorie_uuid'] = '00000000-0000-4000-8000-000000000000';
        $this->client->request('POST', '/api/plats', server: $headers, content: json_encode($payload, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->client->request('GET', '/api/plats/'.UuidGenerator::generate());
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
