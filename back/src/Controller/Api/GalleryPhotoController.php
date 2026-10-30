<?php

namespace App\Controller\Api;

use App\Entity\Photo;
use App\Repository\PhotoRepository;
use App\Repository\RestaurantRepository;
use App\Util\UuidGenerator;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GalleryPhotoController extends AbstractController
{
    #[OA\Get(path: '/api/photos', summary: 'Lister les photos', security: [], responses: [new OA\Response(response: 200, description: 'Photos de la galerie.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PhotoResponse'))]))])]
    #[Route('/api/photos', name: 'api_photos_index', methods: ['GET'])]
    public function index(PhotoRepository $photos): JsonResponse
    {
        return $this->json(['data' => array_map($this->represent(...), $photos->findBy([], ['id' => 'ASC']))]);
    }

    #[OA\Get(path: '/api/photos/{uuid}', summary: 'Consulter une photo', security: [], parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))], responses: [new OA\Response(response: 200, description: 'Photo.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PhotoResponse')])), new OA\Response(response: 404, description: 'Photo introuvable.')])]
    #[Route('/api/photos/{uuid}', name: 'api_photos_show', methods: ['GET'])]
    public function show(string $uuid, PhotoRepository $photos): JsonResponse
    {
        $photo = $photos->findOneBy(['uuid' => $uuid]);
        return $photo ? $this->json(['data' => $this->represent($photo)]) : $this->json(['error' => 'Photo introuvable.'], 404);
    }

    #[OA\Post(path: '/api/photos', summary: 'Ajouter une photo existante', description: 'Chemin local autorisé sous /assets/ ou /uploads/ ; aucun téléversement.', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['titre', 'url_image'], properties: [new OA\Property(property: 'titre', type: 'string', maxLength: 150), new OA\Property(property: 'url_image', type: 'string', example: '/assets/salle.png', maxLength: 255)])), responses: [new OA\Response(response: 201, description: 'Photo créée, URL dans Location.', headers: [new OA\Header(header: 'Location', description: 'URL absolue de la photo créée.', schema: new OA\Schema(type: 'string', format: 'uri'))], content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PhotoResponse')])), new OA\Response(response: 400, description: 'Données invalides.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Administrateur requis.'), new OA\Response(response: 409, description: 'Restaurant non configuré.')])]
    #[Route('/api/photos', name: 'api_photos_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, RestaurantRepository $restaurants, EntityManagerInterface $em, UrlGeneratorInterface $urls): JsonResponse
    {
        $data = $this->input($request);
        if ($data === null) return $this->json(['error' => 'Titre ou chemin de photo invalide.'], 400);
        $restaurant = $restaurants->findOneBy([], ['id' => 'ASC']);
        if (!$restaurant) return $this->json(['error' => 'Restaurant non configuré.'], 409);
        $photo = (new Photo())->setUuid(UuidGenerator::generate())->setRestaurant($restaurant)->setTitre($data['titre'])->setUrlImage($data['url_image']);
        $em->persist($photo); $em->flush();
        return $this->json(['data' => $this->represent($photo)], 201, ['Location' => $urls->generate('api_photos_show', ['uuid' => $photo->getUuid()], UrlGeneratorInterface::ABSOLUTE_URL)]);
    }

    #[OA\Post(path: '/api/photos/upload', summary: 'Ajouter une image depuis un fichier', requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['titre', 'image'], properties: [
        new OA\Property(property: 'titre', type: 'string', maxLength: 150),
        new OA\Property(property: 'image', type: 'string', format: 'binary'),
    ], type: 'object'))), responses: [
        new OA\Response(response: 201, description: 'Photo ajoutée.', headers: [new OA\Header(header: 'Location', description: 'URL absolue de la photo créée.', schema: new OA\Schema(type: 'string', format: 'uri'))], content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PhotoResponse')])),
        new OA\Response(response: 400, description: 'Image ou titre invalide.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Administrateur requis.'),
        new OA\Response(response: 409, description: 'Restaurant non configuré.'),
    ])]
    #[Route('/api/photos/upload', name: 'api_photos_upload', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function upload(Request $request, RestaurantRepository $restaurants, EntityManagerInterface $em, UrlGeneratorInterface $urls): JsonResponse
    {
        $restaurant = $restaurants->findOneBy([], ['id' => 'ASC']);
        if (!$restaurant) return $this->json(['error' => 'Restaurant non configuré.'], 409);
        $file = $this->storeImage($request);
        if ($file === null) return $this->json(['error' => 'Titre ou image invalide (JPEG, PNG ou WebP; maximum 3 Mo).'], 400);
        try {
            $photo = (new Photo())->setUuid(UuidGenerator::generate())->setRestaurant($restaurant)
                ->setTitre($file['titre'])->setUrlImage($file['url']);
            $em->persist($photo);
            $em->flush();
        } catch (\Throwable $error) {
            @unlink($file['path']);
            throw $error;
        }
        return $this->json(['data' => $this->represent($photo)], 201, [
            'Location' => $urls->generate('api_photos_show', ['uuid' => $photo->getUuid()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[OA\Post(path: '/api/photos/{uuid}/image', summary: 'Remplacer le fichier d’une photo', parameters: [
        new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ], requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(
        required: ['titre', 'image'], properties: [new OA\Property(property: 'titre', type: 'string'), new OA\Property(property: 'image', type: 'string', format: 'binary')], type: 'object'
    ))), responses: [
        new OA\Response(response: 200, description: 'Photo mise à jour.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PhotoResponse')])),
        new OA\Response(response: 400, description: 'Image ou titre invalide.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
        new OA\Response(response: 403, description: 'Administrateur requis.'),
        new OA\Response(response: 404, description: 'Photo introuvable.'),
    ])]
    #[Route('/api/photos/{uuid}/image', name: 'api_photos_replace_image', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function replaceImage(string $uuid, Request $request, PhotoRepository $photos, EntityManagerInterface $em): JsonResponse
    {
        $photo = $photos->findOneBy(['uuid' => $uuid]);
        if (!$photo) return $this->json(['error' => 'Photo introuvable.'], 404);
        $file = $this->storeImage($request);
        if ($file === null) return $this->json(['error' => 'Titre ou image invalide (JPEG, PNG ou WebP; maximum 3 Mo).'], 400);
        $oldUrl = $photo->getUrlImage();
        try {
            $photo->setTitre($file['titre'])->setUrlImage($file['url'])->setDateModification(new \DateTimeImmutable());
            $em->flush();
        } catch (\Throwable $error) {
            @unlink($file['path']);
            throw $error;
        }
        $this->deleteUnusedUpload($oldUrl, $photos);
        return $this->json(['data' => $this->represent($photo)]);
    }

    #[OA\Put(path: '/api/photos/{uuid}', summary: 'Modifier une photo', parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['titre', 'url_image'], properties: [new OA\Property(property: 'titre', type: 'string', maxLength: 150), new OA\Property(property: 'url_image', type: 'string', maxLength: 255, example: '/assets/salle.png')])), responses: [new OA\Response(response: 200, description: 'Photo modifiée.', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PhotoResponse')])), new OA\Response(response: 400, description: 'Données invalides.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Administrateur requis.'), new OA\Response(response: 404, description: 'Photo introuvable.')])]
    #[Route('/api/photos/{uuid}', name: 'api_photos_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(string $uuid, Request $request, PhotoRepository $photos, EntityManagerInterface $em): JsonResponse
    {
        $photo = $photos->findOneBy(['uuid' => $uuid]);
        if (!$photo) return $this->json(['error' => 'Photo introuvable.'], 404);
        $data = $this->input($request);
        if ($data === null) return $this->json(['error' => 'Titre ou chemin de photo invalide.'], 400);
        $oldUrl = $photo->getUrlImage();
        $photo->setTitre($data['titre'])->setUrlImage($data['url_image'])->setDateModification(new \DateTimeImmutable());
        $em->flush();
        if ($oldUrl !== $photo->getUrlImage()) $this->deleteUnusedUpload($oldUrl, $photos);
        return $this->json(['data' => $this->represent($photo)]);
    }

    #[OA\Delete(path: '/api/photos/{uuid}', summary: 'Supprimer une photo', description: 'Supprime la référence et, pour un fichier téléversé qui n’est plus référencé, le fichier physique.', parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))], responses: [new OA\Response(response: 204, description: 'Référence supprimée.'), new OA\Response(response: 401, description: 'Authentification requise.'), new OA\Response(response: 403, description: 'Administrateur requis.'), new OA\Response(response: 404, description: 'Photo introuvable.')])]
    #[Route('/api/photos/{uuid}', name: 'api_photos_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(string $uuid, PhotoRepository $photos, EntityManagerInterface $em): JsonResponse
    {
        $photo = $photos->findOneBy(['uuid' => $uuid]);
        if (!$photo) return $this->json(['error' => 'Photo introuvable.'], 404);
        $oldUrl = $photo->getUrlImage();
        $em->remove($photo); $em->flush();
        $this->deleteUnusedUpload($oldUrl, $photos);
        return new JsonResponse(null, 204);
    }

    private function input(Request $request): ?array
    {
        try { $input = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR); } catch (\JsonException) { return null; }
        if (!is_object($input) || count(get_object_vars($input)) !== 2 || !isset($input->titre, $input->url_image)
            || !is_string($input->titre) || !is_string($input->url_image)) return null;
        $titre = trim($input->titre); $url = $input->url_image;
        if ($titre === '' || mb_strlen($titre) > 150 || strlen($url) > 255
            || !preg_match('~^/(?:assets/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+|uploads/[a-f0-9]{32})\.(?:png|jpe?g|webp|gif)$~iD', $url)) return null;
        return ['titre' => $titre, 'url_image' => $url];
    }

    private function represent(Photo $photo): array
    {
        return ['uuid' => $photo->getUuid(), 'titre' => $photo->getTitre(), 'url_image' => $photo->getUrlImage()];
    }

    private function deleteUnusedUpload(?string $url, PhotoRepository $photos): void
    {
        if (!$url || !preg_match('~^/uploads/[a-f0-9]{32}\.(?:jpg|png|webp)$~D', $url)
            || $photos->findOneBy(['urlImage' => $url]) !== null) return;
        $path = $this->getParameter('kernel.project_dir').'/public'.$url;
        if (is_file($path)) unlink($path);
    }

    /** @return array{titre:string,url:string,path:string}|null */
    private function storeImage(Request $request): ?array
    {
        $titre = $request->request->get('titre');
        $image = $request->files->get('image');
        if (!is_string($titre) || trim($titre) === '' || mb_strlen(trim($titre)) > 150
            || !$image instanceof \Symfony\Component\HttpFoundation\File\UploadedFile
            || !$image->isValid() || $image->getSize() === false || $image->getSize() > 3 * 1024 * 1024) return null;
        $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = $image->getMimeType();
        $dimensions = @getimagesize($image->getPathname());
        if (!isset($types[$mime]) || $dimensions === false || ($dimensions['mime'] ?? null) !== $mime
            || $dimensions[0] > 10000 || $dimensions[1] > 10000 || $dimensions[0] * $dimensions[1] > 40000000) return null;
        $name = bin2hex(random_bytes(16)).'.'.$types[$mime];
        $directory = $this->getParameter('kernel.project_dir').'/public/uploads';
        $image->move($directory, $name);
        return ['titre' => trim($titre), 'url' => '/uploads/'.$name, 'path' => $directory.'/'.$name];
    }
}
