<?php

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiDocumentationTest extends WebTestCase
{
    public function testSwaggerUiIsPublicAndLoadsSuccessfully(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/doc');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/html; charset=UTF-8');
        self::assertStringContainsString('Quai Antique API', $client->getResponse()->getContent());
    }

    public function testOpenApiContractDocumentsAllRoutesAndSecurityRules(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/doc.json');

        self::assertResponseIsSuccessful();
        $openApi = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('3.0.0', $openApi['openapi']);
        self::assertSame('Quai Antique API', $openApi['info']['title']);
        self::assertArrayHasKey('ApiToken', $openApi['components']['securitySchemes']);
        self::assertSame([
            'type' => 'apiKey',
            'name' => 'X-AUTH-TOKEN',
            'in' => 'header',
        ], $openApi['components']['securitySchemes']['ApiToken']);
        self::assertSame([['ApiToken' => []]], $openApi['security']);

        $operationCount = 0;
        foreach ($openApi['paths'] as $path => $methods) {
            self::assertStringStartsWith('/api/', $path);
            foreach ($methods as $operation) {
                ++$operationCount;
                self::assertNotEmpty($operation['summary'] ?? null, $path.' requires a summary.');
                self::assertNotEmpty($operation['responses'] ?? [], $path.' requires responses.');
                self::assertArrayNotHasKey('default', $operation['responses']);
                foreach (['200', '201'] as $successStatus) {
                    if (isset($operation['responses'][$successStatus])) {
                        self::assertArrayHasKey('application/json', $operation['responses'][$successStatus]['content'] ?? [], $path.' '.$successStatus.' requires a JSON response content type.');
                        self::assertArrayHasKey('schema', $operation['responses'][$successStatus]['content']['application/json'], $path.' '.$successStatus.' requires a documented response schema.');
                    }
                }
            }
        }
        self::assertGreaterThanOrEqual(20, $operationCount);

        self::assertSame([], $openApi['paths']['/api/categories']['get']['security']);
        self::assertSame([], $openApi['paths']['/api/registration']['post']['security']);
        self::assertSame([], $openApi['paths']['/api/login']['post']['security']);
        self::assertArrayNotHasKey('security', $openApi['paths']['/api/categories']['post']);
        self::assertArrayHasKey('requestBody', $openApi['paths']['/api/registration']['post']);
        self::assertArrayHasKey('Location', $openApi['paths']['/api/categories']['post']['responses']['201']['headers']);
        self::assertArrayNotHasKey('/api/doc.json', $openApi['paths']);
        self::assertArrayNotHasKey('/api/doc', $openApi['paths']);
    }
}
