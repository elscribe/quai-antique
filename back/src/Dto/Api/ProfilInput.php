<?php

namespace App\Dto\Api;

final class ProfilInput
{
    public ?string $prenom = null;

    public ?string $nom = null;

    public ?string $email = null;

    public ?int $nbConvivesDefaut = null;

    public ?string $allergies = null;

    public ?string $password = null;

    public ?string $currentPassword = null;
}
