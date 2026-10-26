<?php

namespace App\Controller\Api;

use App\Entity\Categorie;
use App\Repository\CategorieRepository;
use App\Util\UuidGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CategorieController extends AbstractController
{
    #[Route('/api/categories', name: 'api_categories_index', methods: ['GET'])]
    public function index(CategorieRepository $categorieRepository): JsonResponse
    {
        $categories = $categorieRepository->findBy([], ['titre' => 'ASC']);

        $data = array_map(
            static fn (Categorie $categorie): array => self::serializeCategory($categorie),
            $categories
        );

        return new JsonResponse(['data' => $data]);
    }

    #[Route('/api/categories', name: 'api_categories_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $payload = $request->toArray();
        $titre = $payload['titre'] ?? null;

        if (!is_string($titre) || trim($titre) === '') {
            return new JsonResponse(
                ['error' => 'Le titre est obligatoire.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $titre = trim($titre);

        if (!$this->hasValidTitleLength($titre)) {
            return new JsonResponse(
                ['error' => 'Le titre ne peut pas dépasser 100 caractères.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $categorie = new Categorie();
        $categorie->setUuid(UuidGenerator::generate());
        $categorie->setTitre($titre);

        $entityManager->persist($categorie);
        $entityManager->flush();

        return new JsonResponse(
            ['data' => self::serializeCategory($categorie)],
            Response::HTTP_CREATED
        );
    }

    #[Route('/api/categories/{uuid}', name: 'api_categories_show', methods: ['GET'])]
    public function show(string $uuid, CategorieRepository $categorieRepository): JsonResponse
    {
        $categorie = $categorieRepository->findOneBy(['uuid' => $uuid]);

        if ($categorie === null) {
            return new JsonResponse(
                ['error' => 'Catégorie introuvable.'],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse(['data' => self::serializeCategory($categorie)]);
    }

    #[Route('/api/categories/{uuid}', name: 'api_categories_update', methods: ['PUT'])]
    public function update(
        string $uuid,
        Request $request,
        CategorieRepository $categorieRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $categorie = $categorieRepository->findOneBy(['uuid' => $uuid]);

        if ($categorie === null) {
            return new JsonResponse(
                ['error' => 'Catégorie introuvable.'],
                Response::HTTP_NOT_FOUND
            );
        }

        $payload = $request->toArray();
        $titre = $payload['titre'] ?? null;

        if (!is_string($titre) || trim($titre) === '') {
            return new JsonResponse(
                ['error' => 'Le titre est obligatoire.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $titre = trim($titre);

        if (!$this->hasValidTitleLength($titre)) {
            return new JsonResponse(
                ['error' => 'Le titre ne peut pas dépasser 100 caractères.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $categorie->setTitre($titre);
        $categorie->setDateModification(new \DateTimeImmutable());

        $entityManager->flush();

        return new JsonResponse(['data' => self::serializeCategory($categorie)]);
    }

    #[Route('/api/categories/{uuid}', name: 'api_categories_delete', methods: ['DELETE'])]
    public function delete(
        string $uuid,
        CategorieRepository $categorieRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $categorie = $categorieRepository->findOneBy(['uuid' => $uuid]);

        if ($categorie === null) {
            return new JsonResponse(
                ['error' => 'Catégorie introuvable.'],
                Response::HTTP_NOT_FOUND
            );
        }

        if ($categorie->getPlats()->count() > 0 || $categorie->getMenuCategories()->count() > 0) {
            return new JsonResponse(
                ['error' => 'Cette catégorie ne peut pas être supprimée car elle est utilisée par des plats ou des menus.'],
                Response::HTTP_CONFLICT
            );
        }

        $entityManager->remove($categorie);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array{uuid: ?string, titre: ?string} */
    private static function serializeCategory(Categorie $categorie): array
    {
        return [
            'uuid' => $categorie->getUuid(),
            'titre' => $categorie->getTitre(),
        ];
    }

    private function hasValidTitleLength(string $titre): bool
    {
        $length = iconv_strlen($titre, 'UTF-8');

        return $length !== false && $length <= 100;
    }

}
