<?php

namespace App\Controller\Api;

use App\Entity\Menu;
use App\Entity\MenuCategorie;
use App\Repository\CategorieRepository;
use App\Repository\MenuRepository;
use App\Repository\RestaurantRepository;
use App\Service\ReservationService;
use App\Util\UuidGenerator;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MenuController
{
    private const ITEM_PATH = '/api/menus/{uuid}';

    #[OA\Get(path: '/api/menus', summary: 'Lister les menus du restaurant', security: [], responses: [
        new OA\Response(response: 200, description: 'Menus enregistrés en base.'),
    ])]
    #[Route('/api/menus', name: 'api_menus_index', methods: ['GET'])]
    public function index(MenuRepository $menus): JsonResponse
    {
        return new JsonResponse(['data' => array_map(
            $this->represent(...),
            $menus->findBy([], ['titre' => 'ASC'])
        )]);
    }

    #[OA\Get(path: '/api/menus/{uuid}', summary: 'Consulter un menu', security: [], parameters: [
        new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ], responses: [
        new OA\Response(response: 200, description: 'Menu trouvé.'),
        new OA\Response(response: 404, description: 'Menu introuvable.'),
    ])]
    #[Route(self::ITEM_PATH, name: 'api_menus_show', methods: ['GET'])]
    public function show(string $uuid, MenuRepository $menus): JsonResponse
    {
        $menu = $menus->findOneBy(['uuid' => $uuid]);
        return $menu === null
            ? new JsonResponse(['error' => 'Menu introuvable.'], Response::HTTP_NOT_FOUND)
            : new JsonResponse(['data' => $this->represent($menu)]);
    }

    #[OA\Post(path: '/api/menus', summary: 'Créer un menu', requestBody: new OA\RequestBody(
        required: true, content: new OA\JsonContent(required: ['titre', 'description', 'prix', 'categories'], properties: [
            new OA\Property(property: 'titre', type: 'string', example: 'Découverte'),
            new OA\Property(property: 'description', type: 'string', example: 'Entrée et plat au choix.'),
            new OA\Property(property: 'prix', type: 'string', example: '29.00'),
            new OA\Property(property: 'categories', type: 'array', minItems: 1, items: new OA\Items(type: 'string', format: 'uuid')),
        ])
    ), responses: [
        new OA\Response(response: 201, description: 'Menu créé; son URL est fournie dans Location.'),
        new OA\Response(response: 400, description: 'Données invalides.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Rôle administrateur requis.'),
        new OA\Response(response: 409, description: 'Restaurant non configuré.'),
        new OA\Response(response: 422, description: 'Catégorie introuvable.'),
    ])]
    #[Route('/api/menus', name: 'api_menus_create', methods: ['POST'])]
    public function create(Request $request, RestaurantRepository $restaurants, CategorieRepository $categories, EntityManagerInterface $em, UrlGeneratorInterface $urls): JsonResponse
    {
        $input = $this->readInput($request);
        if ($input instanceof JsonResponse) {
            return $input;
        }
        $restaurant = $restaurants->findOneBy([], ['id' => 'ASC']);
        if ($restaurant === null) {
            return new JsonResponse(['error' => 'Le restaurant doit être configuré avant de créer un menu.'], Response::HTTP_CONFLICT);
        }
        $selected = $this->resolveCategories($input['categories'], $categories);
        if ($selected instanceof JsonResponse) return $selected;

        $menu = (new Menu())->setUuid(UuidGenerator::generate())->setRestaurant($restaurant);
        $this->apply($menu, $input);
        $em->persist($menu);
        $this->setCategories($menu, $selected, $em);
        $em->flush();
        return new JsonResponse(
            ['data' => $this->represent($menu)],
            Response::HTTP_CREATED,
            ['Location' => $urls->generate('api_menus_show', ['uuid' => $menu->getUuid()], UrlGeneratorInterface::ABSOLUTE_URL)]
        );
    }

    #[OA\Put(path: '/api/menus/{uuid}', summary: 'Modifier un menu', parameters: [
        new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['titre', 'description', 'prix', 'categories'], properties: [
            new OA\Property(property: 'titre', type: 'string'),
            new OA\Property(property: 'description', type: 'string'),
            new OA\Property(property: 'prix', type: 'string', example: '29.00'),
            new OA\Property(property: 'categories', type: 'array', minItems: 1, items: new OA\Items(type: 'string', format: 'uuid')),
        ]
    )), responses: [
        new OA\Response(response: 200, description: 'Menu modifié.'),
        new OA\Response(response: 400, description: 'Données invalides.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Rôle administrateur requis.'),
        new OA\Response(response: 404, description: 'Menu introuvable.'),
        new OA\Response(response: 422, description: 'Catégorie introuvable.'),
    ])]
    #[Route(self::ITEM_PATH, name: 'api_menus_update', methods: ['PUT'])]
    public function update(string $uuid, Request $request, MenuRepository $menus, CategorieRepository $categories, EntityManagerInterface $em): JsonResponse
    {
        $menu = $menus->findOneBy(['uuid' => $uuid]);
        if ($menu === null) {
            return new JsonResponse(['error' => 'Menu introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $input = $this->readInput($request);
        if ($input instanceof JsonResponse) {
            return $input;
        }
        $selected = $this->resolveCategories($input['categories'], $categories);
        if ($selected instanceof JsonResponse) return $selected;
        $this->apply($menu, $input);
        $this->setCategories($menu, $selected, $em);
        $menu->setDateModification(new \DateTimeImmutable());
        $em->flush();
        return new JsonResponse(['data' => $this->represent($menu)]);
    }

    #[OA\Delete(path: '/api/menus/{uuid}', summary: 'Supprimer un menu', parameters: [
        new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ], responses: [
        new OA\Response(response: 204, description: 'Menu supprimé.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Rôle administrateur requis.'),
        new OA\Response(response: 404, description: 'Menu introuvable.'),
        new OA\Response(response: 409, description: 'Le dernier menu ne peut pas être supprimé.'),
    ])]
    #[Route(self::ITEM_PATH, name: 'api_menus_delete', methods: ['DELETE'])]
    public function delete(string $uuid, MenuRepository $menus, EntityManagerInterface $em, ReservationService $locking): Response
    {
        $menu = $menus->findOneBy(['uuid' => $uuid]);
        if ($menu === null) {
            return new JsonResponse(['error' => 'Menu introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $restaurant = $menu->getRestaurant();
        $em->getConnection()->beginTransaction();
        try {
            $locking->lockRestaurant($restaurant);
            if ($menus->count(['restaurant' => $restaurant]) <= 1) {
                $em->getConnection()->rollBack();
                return new JsonResponse(['error' => 'Le restaurant doit conserver au moins un menu.'], Response::HTTP_CONFLICT);
            }
            $em->remove($menu);
            $em->flush();
            $em->getConnection()->commit();
            return new Response(null, Response::HTTP_NO_CONTENT);
        } catch (\Throwable $error) {
            if ($em->getConnection()->isTransactionActive()) $em->getConnection()->rollBack();
            throw $error;
        }
    }

    private function represent(Menu $menu): array
    {
        $categories = [];
        foreach ($menu->getMenuCategories() as $link) {
            $categorie = $link->getCategorie();
            if ($categorie !== null) $categories[] = ['uuid' => $categorie->getUuid(), 'titre' => $categorie->getTitre()];
        }
        return ['uuid' => $menu->getUuid(), 'titre' => $menu->getTitre(), 'description' => $menu->getDescription(), 'prix' => $menu->getPrix(), 'categories' => $categories];
    }

    private function readInput(Request $request): array|JsonResponse
    {
        try {
            $input = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }
        if (!is_array($input) || array_is_list($input)
            || !is_string($input['titre'] ?? null)
            || !is_string($input['description'] ?? null)
            || !(is_string($input['prix'] ?? null) || is_int($input['prix'] ?? null) || is_float($input['prix'] ?? null))
            || !is_array($input['categories'] ?? null) || !array_is_list($input['categories'])
            || count($input['categories']) < 1 || count($input['categories']) > 50) {
            return new JsonResponse(['error' => 'Titre, description, prix et au moins une catégorie sont requis.'], Response::HTTP_BAD_REQUEST);
        }
        foreach ($input['categories'] as $uuid) {
            if (!is_string($uuid) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $uuid)) {
                return new JsonResponse(['error' => 'Identifiant de catégorie invalide.'], Response::HTTP_BAD_REQUEST);
            }
        }
        if (count(array_unique($input['categories'])) !== count($input['categories'])) {
            return new JsonResponse(['error' => 'Une catégorie ne peut être répétée.'], Response::HTTP_BAD_REQUEST);
        }
        $titre = trim($input['titre']);
        $description = trim($input['description']);
        $prix = (string) $input['prix'];
        if ($titre === '' || mb_strlen($titre) > 150 || $description === '' || mb_strlen($description) > 5000
            || !preg_match('/^(?:0|[1-9][0-9]{0,3})(?:\.[0-9]{1,2})?$/D', $prix)) {
            return new JsonResponse(['error' => 'Titre, description ou prix invalide.'], Response::HTTP_BAD_REQUEST);
        }
        return ['titre' => $titre, 'description' => $description, 'prix' => number_format((float) $prix, 2, '.', ''), 'categories' => $input['categories']];
    }

    private function apply(Menu $menu, array $input): void
    {
        $menu->setTitre($input['titre'])->setDescription($input['description'])->setPrix($input['prix']);
    }

    private function resolveCategories(array $uuids, CategorieRepository $categories): array|JsonResponse
    {
        $selected = $categories->findBy(['uuid' => $uuids]);
        return count($selected) === count($uuids)
            ? $selected
            : new JsonResponse(['error' => 'Une catégorie est introuvable.'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function setCategories(Menu $menu, array $selected, EntityManagerInterface $em): void
    {
        $wanted = [];
        foreach ($selected as $category) $wanted[$category->getUuid()] = $category;
        foreach ($menu->getMenuCategories()->toArray() as $link) {
            $id = $link->getCategorie()?->getUuid();
            if (!isset($wanted[$id])) {
                $menu->getMenuCategories()->removeElement($link);
                $em->remove($link);
            } else {
                unset($wanted[$id]);
            }
        }
        foreach ($wanted as $category) {
            $link = (new MenuCategorie())->setCategorie($category);
            $menu->addMenuCategory($link);
            $em->persist($link);
        }
    }
}
