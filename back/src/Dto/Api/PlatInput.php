<?php

namespace App\Dto\Api;

use Symfony\Component\Serializer\Attribute\SerializedName;

final class PlatInput
{
    public ?string $titre = null;

    public ?string $description = null;

    public mixed $prix = null;

    #[SerializedName('categorie_uuid')]
    public ?string $categorieUuid = null;
}
