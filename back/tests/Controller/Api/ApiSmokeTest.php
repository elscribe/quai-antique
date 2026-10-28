<?php

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiSmokeTest extends WebTestCase
{
    public function testHealthEndpointReturnsTheExpectedServiceIdentity(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok","application":"Quai Antique API"}',
            $client->getResponse()->getContent()
        );
    }

}
