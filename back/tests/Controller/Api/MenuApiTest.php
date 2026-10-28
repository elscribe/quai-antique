<?php

namespace App\Tests\Controller\Api;

use App\Entity\Restaurant;
use App\Util\UuidGenerator;
use Symfony\Component\HttpFoundation\Response;

final class MenuApiTest extends ApiTestCase
{
    public function testAdministratorCanManagePersistedMenus(): void
    {
        $restaurant = (new Restaurant())->setUuid(UuidGenerator::generate())
            ->setNom('Quai Antique')->setHoraireMidi([])->setHoraireSoir([])->setCapaciteMax(40);
        $this->entityManager->persist($restaurant);
        $this->entityManager->flush();
        $category = $this->createTestCategory('Entrées');
        $otherCategory = $this->createTestCategory('Desserts');
        $token = $this->createTestUser('admin@example.test', ['ROLE_ADMIN'], 'admin-token');
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $token];
        $body = ['titre' => 'Découverte', 'description' => 'Entrée et plat au choix', 'prix' => '29.00', 'categories' => [$category->getUuid()]];
        $this->client->request('POST', '/api/menus', server: $headers, content: json_encode($body, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $created = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data'];
        $path = (string) parse_url($this->client->getResponse()->headers->get('Location'), PHP_URL_PATH);
        self::assertSame('29.00', $created['prix']);
        self::assertSame('Entrées', $created['categories'][0]['titre']);
        $second = $body;
        $second['titre'] = 'Z Autre menu';
        $this->client->request('POST', '/api/menus', server: $headers, content: json_encode($second, JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $secondUuid = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']['uuid'];

        $this->client->request('GET', '/api/menus');
        self::assertResponseIsSuccessful();
        self::assertSame($created['uuid'], json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data'][0]['uuid']);
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        $body['titre'] = 'Terroirs';
        $body['categories'] = [$otherCategory->getUuid()];
        $this->client->request('PUT', $path, server: $headers, content: json_encode($body, JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        self::assertSame('Terroirs', json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']['titre']);
        self::assertSame('Desserts', json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']['categories'][0]['titre']);
        $this->client->request('DELETE', $path, server: ['HTTP_X_AUTH_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->client->request('DELETE', '/api/menus/'.$secondUuid, server: ['HTTP_X_AUTH_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testMenusRejectUnauthorizedAndInvalidWrites(): void
    {
        $clientToken = $this->createTestUser();
        $adminToken = $this->createTestUser('admin@example.test', ['ROLE_ADMIN'], 'admin-token');
        $category = $this->createTestCategory();
        $body = json_encode(['titre' => 'Menu', 'description' => 'Description', 'prix' => '29.00', 'categories' => [$category->getUuid()]], JSON_THROW_ON_ERROR);
        $this->client->request('POST', '/api/menus', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->client->request('POST', '/api/menus', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $clientToken], content: $body);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $adminToken];
        $this->client->request('POST', '/api/menus', server: $headers, content: '{"titre":"","description":"x","prix":"-1"}');
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->client->request('POST', '/api/menus', server: $headers, content: $body);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }
}
