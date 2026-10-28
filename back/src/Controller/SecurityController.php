<?php

namespace App\Controller;

use App\Dto\Api\InscriptionInput;
use App\Dto\Api\ProfilInput;
use App\Entity\Utilisateur;
use App\Entity\Reservation;
use App\Repository\UtilisateurRepository;
use App\Security\PasswordPolicy;
use App\Util\UuidGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OpenApi\Attributes as OA;
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
    #[OA\Post(
        path: '/api/registration',
        summary: 'Créer un compte client et recevoir un jeton API',
        security: [],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['prenom', 'nom', 'email', 'password'],
            properties: [
                new OA\Property(property: 'prenom', type: 'string', example: 'Camille'),
                new OA\Property(property: 'nom', type: 'string', example: 'Martin'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'camille@example.test'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'MotDePasse-123!'),
                new OA\Property(property: 'nbConvivesDefaut', type: 'integer', nullable: true, example: 2),
                new OA\Property(property: 'allergies', type: 'string', nullable: true, example: 'Noix'),
            ]
        )),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Compte créé. Le jeton API est renvoyé en clair une seule fois; conserver ce secret de manière sûre.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'prenom', type: 'string', example: 'Camille'),
                        new OA\Property(property: 'nom', type: 'string', example: 'Martin'),
                        new OA\Property(property: 'email', type: 'string', format: 'email'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string', example: 'ROLE_USER')),
                    ]),
                    new OA\Property(property: 'apiToken', type: 'string', example: 'jeton-retourne-une-seule-fois'),
                ])
            ),
            new OA\Response(response: 400, description: 'JSON invalide ou champs obligatoires invalides.'),
            new OA\Response(response: 409, description: 'Cette adresse e-mail est déjà utilisée.'),
        ]
    )]
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
            || mb_strlen($prenom) > 80
            || mb_strlen($nom) > 80
            || mb_strlen($email) > 255
            || !PasswordPolicy::isValid($password)
            || ($input->nbConvivesDefaut !== null && ($input->nbConvivesDefaut < 1 || $input->nbConvivesDefaut > 65535))
            || ($input->allergies !== null && mb_strlen($input->allergies) > 5000)
        ) {
            return new JsonResponse(
                ['error' => 'Vérifiez vos informations : nom et prénom limités à 80 caractères, e-mail valide et mot de passe sécurisé de 8 à 128 caractères.'],
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
        $utilisateur->setNbConvivesDefaut($input->nbConvivesDefaut);
        $utilisateur->setAllergies($input->allergies === null ? null : trim($input->allergies));
        $utilisateur->setMotDePasseHash(
            $passwordHasher->hashPassword($utilisateur, $password)
        );

        $apiToken = $this->issueApiToken($utilisateur);

        $entityManager->persist($utilisateur);
        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return new JsonResponse(['error' => 'Cette adresse e-mail est déjà utilisée.'], Response::HTTP_CONFLICT);
        }

        return new JsonResponse(
            [
                'data' => $this->userData($utilisateur),
                'apiToken' => $apiToken,
            ],
            Response::HTTP_CREATED
        );
    }

    #[OA\Post(
        path: '/api/login',
        summary: 'Authentifier un utilisateur et émettre un nouveau jeton API',
        security: [],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'camille@example.test'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'MotDePasse-123!'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Connexion réussie. Le jeton API est renouvelé.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'prenom', type: 'string'),
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string', example: 'ROLE_USER')),
                ]),
                new OA\Property(property: 'apiToken', type: 'string', example: 'jeton-retourne-une-seule-fois'),
            ])),
            new OA\Response(response: 401, description: 'Identifiants manquants ou invalides.'),
        ]
    )]
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

    #[OA\Get(
        path: '/api/account/me',
        summary: 'Lire le profil du compte authentifié',
        responses: [
            new OA\Response(response: 200, description: 'Profil personnel. Les hashes du mot de passe et du jeton ne sont pas exposés.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'prenom', type: 'string'),
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'nbConvivesDefaut', type: 'integer', nullable: true, example: 2),
                    new OA\Property(property: 'allergies', type: 'string', nullable: true, example: 'Noix'),
                ]),
            ])),
            new OA\Response(response: 401, description: 'Jeton API absent ou invalide.'),
        ]
    )]
    #[Route('/account/me', name: 'account_me', methods: ['GET'])]
    public function me(
        #[CurrentUser] Utilisateur $utilisateur,
        SerializerInterface $serializer
    ): JsonResponse {
        return $this->profileResponse($utilisateur, $serializer);
    }

    #[OA\Put(
        path: '/api/account/me',
        summary: 'Modifier le profil du compte authentifié',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['prenom', 'nom'],
            properties: [
                new OA\Property(property: 'prenom', type: 'string', example: 'Camille'),
                new OA\Property(property: 'nom', type: 'string', example: 'Martin'),
                new OA\Property(property: 'nbConvivesDefaut', type: 'integer', nullable: true, example: 2),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'allergies', type: 'string', nullable: true, example: 'Noix'),
                new OA\Property(property: 'password', type: 'string', format: 'password', nullable: true, example: 'NouveauMotDePasse-123!'),
                new OA\Property(property: 'currentPassword', type: 'string', format: 'password', nullable: true),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Profil modifié. Les hashes du mot de passe et du jeton ne sont pas exposés.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'prenom', type: 'string'),
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'nbConvivesDefaut', type: 'integer', nullable: true),
                    new OA\Property(property: 'allergies', type: 'string', nullable: true),
                ]),
            ])),
            new OA\Response(response: 400, description: 'JSON invalide ou champs de profil invalides.'),
            new OA\Response(response: 401, description: 'Jeton API absent ou invalide.'),
            new OA\Response(response: 409, description: 'Cette adresse e-mail est déjà utilisée.'),
        ]
    )]
    #[Route('/account/me', name: 'account_update', methods: ['PUT'])]
    public function edit(
        Request $request,
        #[CurrentUser] Utilisateur $utilisateur,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        UserPasswordHasherInterface $passwordHasher,
        UtilisateurRepository $utilisateurRepository
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
        $email = trim($input->email ?? (string) $utilisateur->getEmail());

        if ($prenom === '' || $nom === '' || mb_strlen($prenom) > 80 || mb_strlen($nom) > 80) {
            return new JsonResponse(
                ['error' => 'Le prénom et le nom sont obligatoires et limités à 80 caractères.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (mb_strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return new JsonResponse(['error' => 'Adresse e-mail invalide.'], Response::HTTP_BAD_REQUEST);
        }
        $existing = $utilisateurRepository->findOneBy(['email' => $email]);
        if ($existing !== null && $existing->getId() !== $utilisateur->getId()) {
            return new JsonResponse(['error' => 'Cette adresse e-mail est déjà utilisée.'], Response::HTTP_CONFLICT);
        }

        if ($input->nbConvivesDefaut !== null && ($input->nbConvivesDefaut < 1 || $input->nbConvivesDefaut > 65535)) {
            return new JsonResponse(
                ['error' => 'Le nombre de convives par défaut doit être positif.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($input->allergies !== null && mb_strlen($input->allergies) > 5000) {
            return new JsonResponse(['error' => 'La mention des allergies est trop longue.'], Response::HTTP_BAD_REQUEST);
        }

        if ($input->password !== null && (!PasswordPolicy::isValid($input->password)
            || $input->currentPassword === null || !$passwordHasher->isPasswordValid($utilisateur, $input->currentPassword))) {
            return new JsonResponse(
                ['error' => 'Mot de passe actuel incorrect ou nouveau mot de passe non conforme.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $utilisateur->setPrenom($prenom);
        $utilisateur->setNom($nom);
        $utilisateur->setEmail($email);
        $utilisateur->setNbConvivesDefaut($input->nbConvivesDefaut);
        $utilisateur->setAllergies($input->allergies === null ? null : trim($input->allergies));

        if ($input->password !== null) {
            $utilisateur->setMotDePasseHash(
                $passwordHasher->hashPassword($utilisateur, $input->password)
            );
            $utilisateur->setApiTokenHash(null)->setApiTokenExpiresAt(null);
        }

        $utilisateur->setDateModification(new \DateTimeImmutable());
        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return new JsonResponse(['error' => 'Cette adresse e-mail est déjà utilisée.'], Response::HTTP_CONFLICT);
        }

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
        $utilisateur->setApiTokenHash(hash('sha256', $apiToken))
            ->setApiTokenExpiresAt(new \DateTimeImmutable('+12 hours'));

        return $apiToken;
    }

    #[OA\Post(path: '/api/logout', summary: 'Révoquer le jeton de la session actuelle', responses: [
        new OA\Response(response: 204, description: 'Jeton révoqué.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
    ])]
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(#[CurrentUser] Utilisateur $utilisateur, EntityManagerInterface $entityManager): Response
    {
        $utilisateur->setApiTokenHash(null)->setApiTokenExpiresAt(null);
        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[OA\Delete(path: '/api/account/me', summary: 'Supprimer son compte et ses réservations', requestBody: new OA\RequestBody(
        required: true, content: new OA\JsonContent(required: ['currentPassword'], properties: [
            new OA\Property(property: 'currentPassword', type: 'string', format: 'password'),
        ])
    ), responses: [
        new OA\Response(response: 204, description: 'Compte et réservations supprimés.'),
        new OA\Response(response: 400, description: 'Mot de passe incorrect ou données invalides.'),
        new OA\Response(response: 401, description: 'Authentification requise.'),
    ])]
    #[Route('/account/me', name: 'account_delete', methods: ['DELETE'])]
    public function deleteAccount(Request $request, #[CurrentUser] Utilisateur $utilisateur, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        try {
            $body = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }
        if (!is_array($body) || !is_string($body['currentPassword'] ?? null)
            || !$passwordHasher->isPasswordValid($utilisateur, $body['currentPassword'])) {
            return new JsonResponse(['error' => 'Mot de passe actuel incorrect.'], Response::HTTP_BAD_REQUEST);
        }
        foreach ($entityManager->getRepository(Reservation::class)->findBy(['utilisateur' => $utilisateur]) as $reservation) {
            $entityManager->remove($reservation);
        }
        $entityManager->remove($utilisateur);
        $entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
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
