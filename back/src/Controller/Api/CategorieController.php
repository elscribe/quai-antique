<?php

namespace App\Controller\Api;

use App\Dto\Api\CategorieInput;
use App\Entity\Categorie;
use App\Repository\CategorieRepository;
use App\Util\UuidGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\SerializerInterface;

final class CategorieController extends AbstractController
{
    private const ITEM_ROUTE = '/api/categories/{uuid}';
    private const CATEGORY_NOT_FOUND = 'Catégorie introuvable.';

    #[Route('/api/categories', name: 'api_categories_index', methods: ['GET'])]
    public function index(
        CategorieRepository $categorieRepository,
        SerializerInterface $serializer
    ): JsonResponse {
        $categories = $categorieRepository->findBy([], ['titre' => 'ASC']);

        return $this->serializedJsonResponse(
            $serializer,
            ['data' => $categories],
            ['groups' => ['categorie:read']]
        );
    }

    #[Route('/api/categories', name: 'api_categories_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        UrlGeneratorInterface $urlGenerator
    ): JsonResponse {
        $titre = $this->readTitle($request, $serializer);

        if ($titre instanceof JsonResponse) {
            return $titre;
        }

        $categorie = new Categorie();
        $categorie->setUuid(UuidGenerator::generate());
        $categorie->setTitre($titre);

        $entityManager->persist($categorie);
        $entityManager->flush();
        $location = $urlGenerator->generate(
            'api_categories_show',
            ['uuid' => $categorie->getUuid()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $this->serializedJsonResponse(
            $serializer,
            ['data' => $categorie],
            ['groups' => ['categorie:read']],
            Response::HTTP_CREATED,
            ['Location' => $location]
        );
    }

    #[Route(self::ITEM_ROUTE, name: 'api_categories_show', methods: ['GET'])]
    public function show(
        string $uuid,
        CategorieRepository $categorieRepository,
        SerializerInterface $serializer
    ): JsonResponse {
        $categorie = $categorieRepository->findOneBy(['uuid' => $uuid]);

        if ($categorie === null) {
            return new JsonResponse(
                ['error' => self::CATEGORY_NOT_FOUND],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->serializedJsonResponse(
            $serializer,
            ['data' => $categorie],
            ['groups' => ['categorie:read']]
        );
    }

    #[Route(self::ITEM_ROUTE, name: 'api_categories_update', methods: ['PUT'])]
    public function update(
        string $uuid,
        Request $request,
        CategorieRepository $categorieRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer
    ): JsonResponse {
        $categorie = $categorieRepository->findOneBy(['uuid' => $uuid]);

        if ($categorie === null) {
            return new JsonResponse(
                ['error' => self::CATEGORY_NOT_FOUND],
                Response::HTTP_NOT_FOUND
            );
        }

        $titre = $this->readTitle($request, $serializer);

        if ($titre instanceof JsonResponse) {
            return $titre;
        }

        $categorie->setTitre($titre);
        $categorie->setDateModification(new \DateTimeImmutable());

        $entityManager->flush();

        return $this->serializedJsonResponse(
            $serializer,
            ['data' => $categorie],
            ['groups' => ['categorie:read']]
        );
    }

    #[Route(self::ITEM_ROUTE, name: 'api_categories_delete', methods: ['DELETE'])]
    public function delete(
        string $uuid,
        CategorieRepository $categorieRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $categorie = $categorieRepository->findOneBy(['uuid' => $uuid]);

        if ($categorie === null) {
            return new JsonResponse(
                ['error' => self::CATEGORY_NOT_FOUND],
                Response::HTTP_NOT_FOUND
            );
        }

        if (
            $categorie->getPlats()->count() > 0
            || $categorie->getMenuCategories()->count() > 0
        ) {
            return new JsonResponse(
                ['error' => 'Cette catégorie ne peut pas être supprimée car elle est utilisée par des plats ou des menus.'],
                Response::HTTP_CONFLICT
            );
        }

        $entityManager->remove($categorie);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function readTitle(Request $request, SerializerInterface $serializer): string|JsonResponse
    {
        try {
            $input = $serializer->deserialize($request->getContent(), CategorieInput::class, 'json');
        } catch (NotEncodableValueException|NotNormalizableValueException) {
            return new JsonResponse(
                ['error' => 'Le corps de la requête doit être un objet JSON valide.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        return $this->validateTitle($input->titre);
    }

    private function validateTitle(mixed $titre): string|JsonResponse
    {
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

        return $titre;
    }

    private function hasValidTitleLength(string $titre): bool
    {
        $length = iconv_strlen($titre, 'UTF-8');

        return $length !== false && $length <= 100;
    }

    /** @param array<string, mixed> $context */
    private function serializedJsonResponse(
        SerializerInterface $serializer,
        mixed $data,
        array $context = [],
        int $statusCode = Response::HTTP_OK,
        array $headers = []
    ): JsonResponse {
        return new JsonResponse(
            $serializer->serialize($data, 'json', $context),
            $statusCode,
            $headers,
            true
        );
    }
}
