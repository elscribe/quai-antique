<?php

namespace App\Tests\Controller\Api;

use App\Entity\Restaurant;
use App\Util\UuidGenerator;

final class SettingsAndGalleryApiTest extends ApiTestCase
{
    private function createRestaurant(): void
    {
        $restaurant = (new Restaurant())->setUuid(UuidGenerator::generate())->setNom('Quai Antique')
            ->setHoraireMidi(['debut' => '12:00', 'fin' => '14:00'])
            ->setHoraireSoir(['debut' => '19:00', 'fin' => '21:00'])->setCapaciteMax(40);
        $this->entityManager->persist($restaurant);
        $this->entityManager->flush();
    }

    public function testRestaurantSettingsArePublicToReadAndAdminOnlyToChange(): void
    {
        $this->createRestaurant();
        $clientToken = $this->createTestUser();
        $adminToken = $this->createTestUser('admin@example.test', ['ROLE_ADMIN'], 'admin-token');
        $body = json_encode([
            'horaire_midi' => ['debut' => '12:00', 'fin' => '14:00'],
            'horaire_soir' => ['debut' => '19:00', 'fin' => '21:00'],
            'capacite_max' => 30,
        ], JSON_THROW_ON_ERROR);
        $this->client->request('GET', '/api/restaurant');
        self::assertResponseIsSuccessful();
        $this->client->request('PUT', '/api/restaurant', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $clientToken], content: $body);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('PUT', '/api/restaurant', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $adminToken], content: $body);
        self::assertResponseIsSuccessful();
        self::assertSame(30, json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']['capacite_max']);
        $invalid = str_replace('21:00', '22:00', $body);
        $this->client->request('PUT', '/api/restaurant', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $adminToken], content: $invalid);
        self::assertResponseStatusCodeSame(400);
    }

    public function testPhotoCrudEnforcesAdminAndRejectsUnsafePaths(): void
    {
        $this->createRestaurant();
        $clientToken = $this->createTestUser();
        $adminToken = $this->createTestUser('admin@example.test', ['ROLE_ADMIN'], 'admin-token');
        $body = json_encode(['titre' => 'Salle', 'url_image' => '/assets/salle.png'], JSON_THROW_ON_ERROR);
        $this->client->request('POST', '/api/photos', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $clientToken], content: $body);
        self::assertResponseStatusCodeSame(403);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_AUTH_TOKEN' => $adminToken];
        $this->client->request('POST', '/api/photos', server: $headers, content: '{"titre":"x","url_image":"/assets/../secrets.png"}');
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/api/photos', server: $headers, content: $body);
        self::assertResponseStatusCodeSame(201);
        $uuid = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data']['uuid'];
        $this->client->request('GET', '/api/photos');
        self::assertResponseIsSuccessful();
        self::assertSame('Salle', json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data'][0]['titre']);
        $this->client->request('PUT', '/api/photos/'.$uuid, server: $headers, content: '{"titre":"Terrasse","url_image":"/assets/terrasse.png"}');
        self::assertResponseIsSuccessful();
        $this->client->request('DELETE', '/api/photos/'.$uuid, server: ['HTTP_X_AUTH_TOKEN' => $adminToken]);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', '/api/photos/'.$uuid);
        self::assertResponseStatusCodeSame(404);
    }

    public function testUploadRejectsAClientAndAnImageWithFakeContents(): void
    {
        $this->createRestaurant();
        $clientToken = $this->createTestUser();
        $adminToken = $this->createTestUser('admin@example.test', ['ROLE_ADMIN'], 'admin-token');
        $path = tempnam(sys_get_temp_dir(), 'quai-invalid-image-');
        file_put_contents($path, 'This is not an image');

        try {
            $upload = new \Symfony\Component\HttpFoundation\File\UploadedFile($path, 'fake.png', 'image/png', null, true);
            $this->client->request('POST', '/api/photos/upload', parameters: ['titre' => 'Fausse image'], files: ['image' => $upload], server: ['HTTP_X_AUTH_TOKEN' => $clientToken]);
            self::assertResponseStatusCodeSame(403);
            $this->client->request('POST', '/api/photos/upload', parameters: ['titre' => 'Fausse image'], files: ['image' => $upload], server: ['HTTP_X_AUTH_TOKEN' => $adminToken]);
            self::assertResponseStatusCodeSame(400);
        } finally {
            @unlink($path);
        }
    }

    public function testAdministratorCanUploadAndDeleteAnImage(): void
    {
        $this->createRestaurant();
        $token = $this->createTestUser('admin@example.test', ['ROLE_ADMIN'], 'admin-token');
        $path = tempnam(sys_get_temp_dir(), 'quai-photo-');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a0R0AAAAASUVORK5CYII='));
        $upload = new \Symfony\Component\HttpFoundation\File\UploadedFile($path, 'photo.png', 'image/png', null, true);
        $this->client->request('POST', '/api/photos/upload', parameters: ['titre' => 'Photo test'], files: ['image' => $upload], server: ['HTTP_X_AUTH_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(201);
        $photo = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data'];
        $uploadedFile = static::getContainer()->getParameter('kernel.project_dir').'/public'.$photo['url_image'];
        self::assertFileExists($uploadedFile);
        $replacementPath = tempnam(sys_get_temp_dir(), 'quai-photo-');
        file_put_contents($replacementPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a0R0AAAAASUVORK5CYII='));
        $replacement = new \Symfony\Component\HttpFoundation\File\UploadedFile($replacementPath, 'autre.png', 'image/png', null, true);
        $this->client->request('POST', '/api/photos/'.$photo['uuid'].'/image', parameters: ['titre' => 'Photo modifiée'], files: ['image' => $replacement], server: ['HTTP_X_AUTH_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        self::assertFileDoesNotExist($uploadedFile);
        $updated = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data'];
        $uploadedFile = static::getContainer()->getParameter('kernel.project_dir').'/public'.$updated['url_image'];
        self::assertFileExists($uploadedFile);
        $this->client->request('DELETE', '/api/photos/'.$photo['uuid'], server: ['HTTP_X_AUTH_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(204);
        self::assertFileDoesNotExist($uploadedFile);
    }

    public function testAdministratorCanUploadImageAtThreeMebibytes(): void
    {
        $this->createRestaurant();
        $token = $this->createTestUser('admin@example.test', ['ROLE_ADMIN'], 'admin-token');
        $path = tempnam(sys_get_temp_dir(), 'quai-photo-limit-');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a0R0AAAAASUVORK5CYII=', true);
        self::assertIsString($png);
        file_put_contents($path, $png.str_repeat("\0", 3 * 1024 * 1024 - strlen($png)));
        $uploadedFile = null;

        try {
            $upload = new \Symfony\Component\HttpFoundation\File\UploadedFile($path, 'photo-limite.png', 'image/png', null, true);
            self::assertSame(3 * 1024 * 1024, $upload->getSize());
            self::assertTrue($upload->isValid());
            self::assertSame('image/png', $upload->getMimeType());
            self::assertSame('image/png', getimagesize($path)['mime']);
            $this->client->request('POST', '/api/photos/upload', parameters: ['titre' => 'Photo à la limite'], files: ['image' => $upload], server: ['HTTP_X_AUTH_TOKEN' => $token]);
            self::assertResponseStatusCodeSame(201);
            $photo = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data'];
            $uploadedFile = static::getContainer()->getParameter('kernel.project_dir').'/public'.$photo['url_image'];
            self::assertFileExists($uploadedFile);
            self::assertSame(3 * 1024 * 1024, filesize($uploadedFile));

            $this->client->request('DELETE', '/api/photos/'.$photo['uuid'], server: ['HTTP_X_AUTH_TOKEN' => $token]);
            self::assertResponseStatusCodeSame(204);
            self::assertFileDoesNotExist($uploadedFile);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            if (is_string($uploadedFile) && is_file($uploadedFile)) {
                unlink($uploadedFile);
            }
        }
    }
}
