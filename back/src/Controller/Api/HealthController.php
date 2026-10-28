<?php

namespace App\Controller\Api;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[OA\Get(
        path: '/api/health',
        summary: 'Vérifier que l’API Quai Antique répond',
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'API opérationnelle.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'application', type: 'string', example: 'Quai Antique API'),
                    ]
                )
            ),
        ]
    )]
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'application' => 'Quai Antique API',
        ]);
    }
}
