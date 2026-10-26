<?php

namespace App\Controller\Api;

use App\Entity\Plat;
use App\Repository\CategorieRepository;
use App\Repository\PlatRepository;
use App\Util\UuidGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PlatController extends AbstractController
{
    #[Route('/api/plats', name: 'api_plats_index', methods: ['GET'])]
    public function index(PlatRepository $platRepository): JsonResponse
    {
        $plats = $platRepository->findBy([], ['titre' => 'ASC']);

        $data = array_map(
            static fn (Plat $plat): array => self::serializePlat($plat),
            $plats
        );

        return new JsonResponse(['data' => $data]);
    }

    #[Route('/api/plats', name: 'api_plats_create', methods: ['POST'])]
    public function create(
        Request $request,
        CategorieRepository $categorieRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $input = $this->readInput($request, $categorieRepository);

        if ($input instanceof JsonResponse) {
            return $input;
        }

        $plat = new Plat();
        $plat->setUuid(UuidGenerator::generate());
        $plat->setTitre($input['titre']);
        $plat->setDescription($input['description']);
        $plat->setPrix($input['prix']);
        $plat->setCategorie($input['categorie']);

        $entityManager->persist($plat);
        $entityManager->flush();

        return new JsonResponse(
            ['data' => self::serializePlat($plat)],
            Response::HTTP_CREATED
        );
    }

    #[Route('/api/plats/{uuid}', name: 'api_plats_show', methods: ['GET'])]
    public function show(string $uuid, PlatRepository $platRepository): JsonResponse
    {
        $plat = $platRepository->findOneBy(['uuid' => $uuid]);

        if ($plat === null) {
            return new JsonResponse(
                ['error' => 'Plat introuvable.'],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse(['data' => self::serializePlat($plat)]);
    }

    #[Route('/api/plats/{uuid}', name: 'api_plats_update', methods: ['PUT'])]
    public function update(
        string $uuid,
        Request $request,
        PlatRepository $platRepository,
        CategorieRepository $categorieRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $plat = $platRepository->findOneBy(['uuid' => $uuid]);

        if ($plat === null) {
            return new JsonResponse(
                ['error' => 'Plat introuvable.'],
                Response::HTTP_NOT_FOUND
            );
        }

        $input = $this->readInput($request, $categorieRepository);

        if ($input instanceof JsonResponse) {
            return $input;
        }

        $plat->setTitre($input['titre']);
        $plat->setDescription($input['description']);
        $plat->setPrix($input['prix']);
        $plat->setCategorie($input['categorie']);
        $plat->setDateModification(new \DateTimeImmutable());

        $entityManager->flush();

        return new JsonResponse(['data' => self::serializePlat($plat)]);
    }

    #[Route('/api/plats/{uuid}', name: 'api_plats_delete', methods: ['DELETE'])]
    public function delete(
        string $uuid,
        PlatRepository $platRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $plat = $platRepository->findOneBy(['uuid' => $uuid]);

        if ($plat === null) {
            return new JsonResponse(
                ['error' => 'Plat introuvable.'],
                Response::HTTP_NOT_FOUND
            );
        }

        $entityManager->remove($plat);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array{titre: string, description: string, prix: string, categorie: \App\Entity\Categorie}|JsonResponse */
    private function readInput(Request $request, CategorieRepository $categorieRepository): array|JsonResponse
    {
        $payload = $request->toArray();
        $titre = $payload['titre'] ?? null;
        $description = $payload['description'] ?? null;
        $prix = $payload['prix'] ?? null;
        $categorieUuid = $payload['categorie_uuid'] ?? null;

        if (!is_string($titre) || trim($titre) === '') {
            return new JsonResponse(
                ['error' => 'Le titre est obligatoire.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $titre = trim($titre);
        $titreLength = iconv_strlen($titre, 'UTF-8');

        if ($titreLength === false || $titreLength > 150) {
            return new JsonResponse(
                ['error' => 'Le titre ne peut pas dépasser 150 caractères.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!is_string($description)) {
            return new JsonResponse(
                ['error' => 'La description doit être une chaîne de caractères.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$this->isValidPrice($prix)) {
            return new JsonResponse(
                ['error' => 'Le prix doit être positif, inférieur ou égal à 9999.99, avec au maximum deux décimales.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!is_string($categorieUuid) || trim($categorieUuid) === '') {
            return new JsonResponse(
                ['error' => 'categorie_uuid est obligatoire.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $categorie = $categorieRepository->findOneBy(['uuid' => $categorieUuid]);

        if ($categorie === null) {
            return new JsonResponse(
                ['error' => 'La catégorie indiquée est introuvable.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return [
            'titre' => $titre,
            'description' => $description,
            'prix' => number_format((float) $prix, 2, '.', ''),
            'categorie' => $categorie,
        ];
    }

    private function isValidPrice(mixed $price): bool
    {
        if (!is_string($price) && !is_int($price) && !is_float($price)) {
            return false;
        }

        return preg_match('/^(?:0|[1-9]\d{0,3})(?:\.\d{1,2})?$/D', (string) $price) === 1;
    }

    /** @return array<string, mixed> */
    private static function serializePlat(Plat $plat): array
    {
        return [
            'uuid' => $plat->getUuid(),
            'titre' => $plat->getTitre(),
            'description' => $plat->getDescription(),
            'prix' => $plat->getPrix(),
            'categorie' => [
                'uuid' => $plat->getCategorie()?->getUuid(),
                'titre' => $plat->getCategorie()?->getTitre(),
            ],
        ];
    }
}
