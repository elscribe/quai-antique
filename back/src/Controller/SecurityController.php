<?php

namespace App\Controller;

use App\Dto\Api\InscriptionInput;
use App\Dto\Api\ProfilInput;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Util\UuidGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api', name: 'api_')]
final class SecurityController
{
    #[Route('/registration', name: 'registration', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        UtilisateurRepository $utilisateurRepository,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        try {
            $input = $serializer->deserialize(
                $request->getContent(),
                InscriptionInput::class,
                'json'
            );
        } catch (NotEncodableValueException | NotNormalizableValueException) {
            return new JsonResponse(
                ['error' => 'Le corps doit contenir un JSON valide.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $prenom = trim($input->prenom ?? '');
        $nom = trim($input->nom ?? '');
        $email = trim($input->email ?? '');
        $password = $input->password ?? '';

        if (
            $prenom === ''
            || $nom === ''
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || $password === ''
        ) {
            return new JsonResponse(
                ['error' => 'Prénom, nom, adresse e-mail valide et mot de passe sont obligatoires.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($utilisateurRepository->findOneBy(['email' => $email]) !== null) {
            return new JsonResponse(
                ['error' => 'Cette adresse e-mail est déjà utilisée.'],
                Response::HTTP_CONFLICT
            );
        }

        $utilisateur = new Utilisateur();
        $utilisateur->setUuid(UuidGenerator::generate());
        $utilisateur->setPrenom($prenom);
        $utilisateur->setNom($nom);
        $utilisateur->setEmail($email);
        $utilisateur->setRoles(['ROLE_USER']);
        $utilisateur->setMotDePasseHash(
            $passwordHasher->hashPassword($utilisateur, $password)
        );

        $apiToken = $this->issueApiToken($utilisateur);

        $entityManager->persist($utilisateur);
        $entityManager->flush();

        return new JsonResponse(
            [
                'data' => $this->userData($utilisateur),
                'apiToken' => $apiToken,
            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        #[CurrentUser] ?Utilisateur $utilisateur,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if ($utilisateur === null) {
            return new JsonResponse(
                ['error' => 'Identifiants manquants ou invalides.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $apiToken = $this->issueApiToken($utilisateur);
        $entityManager->flush();

        return new JsonResponse([
            'data' => $this->userData($utilisateur),
            'apiToken' => $apiToken,
        ]);
    }

    #[Route('/account/me', name: 'account_me', methods: ['GET'])]
    public function me(
        #[CurrentUser] Utilisateur $utilisateur,
        SerializerInterface $serializer
    ): JsonResponse {
        return $this->profileResponse($utilisateur, $serializer);
    }

    #[Route('/account/me', name: 'account_update', methods: ['PUT'])]
    public function edit(
        Request $request,
        #[CurrentUser] Utilisateur $utilisateur,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        try {
            $input = $serializer->deserialize(
                $request->getContent(),
                ProfilInput::class,
                'json'
            );
        } catch (NotEncodableValueException | NotNormalizableValueException) {
            return new JsonResponse(
                ['error' => 'Le corps doit contenir un JSON valide.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $prenom = trim($input->prenom ?? '');
        $nom = trim($input->nom ?? '');

        if ($prenom === '' || $nom === '' || mb_strlen($prenom) > 80 || mb_strlen($nom) > 80) {
            return new JsonResponse(
                ['error' => 'Le prénom et le nom sont obligatoires et limités à 80 caractères.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($input->nbConvivesDefaut !== null && $input->nbConvivesDefaut < 1) {
            return new JsonResponse(
                ['error' => 'Le nombre de convives par défaut doit être positif.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($input->password !== null && trim($input->password) === '') {
            return new JsonResponse(
                ['error' => 'Le mot de passe ne peut pas être vide.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $utilisateur->setPrenom($prenom);
        $utilisateur->setNom($nom);
        $utilisateur->setNbConvivesDefaut($input->nbConvivesDefaut);
        $utilisateur->setAllergies($input->allergies === null ? null : trim($input->allergies));

        if ($input->password !== null) {
            $utilisateur->setMotDePasseHash(
                $passwordHasher->hashPassword($utilisateur, $input->password)
            );
        }

        $utilisateur->setDateModification(new \DateTimeImmutable());
        $entityManager->flush();

        return $this->profileResponse($utilisateur, $serializer);
    }

    private function profileResponse(Utilisateur $utilisateur, SerializerInterface $serializer): JsonResponse
    {
        return new JsonResponse(
            $serializer->serialize(
                ['data' => $utilisateur],
                'json',
                ['groups' => ['utilisateur:profile']]
            ),
            Response::HTTP_OK,
            [],
            true
        );
    }

    private function issueApiToken(Utilisateur $utilisateur): string
    {
        $apiToken = bin2hex(random_bytes(32));
        $utilisateur->setApiTokenHash(hash('sha256', $apiToken));

        return $apiToken;
    }

    /** @return array{uuid: ?string, prenom: ?string, nom: ?string, email: ?string, roles: list<string>} */
    private function userData(Utilisateur $utilisateur): array
    {
        return [
            'uuid' => $utilisateur->getUuid(),
            'prenom' => $utilisateur->getPrenom(),
            'nom' => $utilisateur->getNom(),
            'email' => $utilisateur->getEmail(),
            'roles' => $utilisateur->getRoles(),
        ];
    }
}
