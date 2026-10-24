<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MenuController
{
    #[Route('/api/menus', name: 'api_menus_index', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'menus' => [
                [
                    'name' => 'Découverte',
                    'price' => 29,
                ],
                [
                    'name' => 'Terroirs',
                    'price' => 39,
                ],
                [
                    'name' => 'Voyage Savoyard',
                    'price' => 89,
                ],
            ],
        ]);
    }
}
