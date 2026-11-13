<?php

namespace App\CountryPacks\Cameroon;

use App\CountryPacks\Contracts\CountryPack;

/**
 * Included with every agency. Field list to be confirmed with the client in milestone 2.
 */
final class CameroonPack implements CountryPack
{
    public function code(): string
    {
        return 'CM';
    }

    public function currency(): string
    {
        return 'XAF';
    }

    public function freelancerFields(): array
    {
        return [
            'cni_number' => [
                'label' => ['en' => 'National ID card (CNI) number', 'fr' => 'Numéro de CNI'],
                'rules' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
            ],
            'region' => [
                'label' => ['en' => 'Region', 'fr' => 'Région'],
                'rules' => ['nullable', 'in:adamaoua,centre,est,extreme-nord,littoral,nord,nord-ouest,ouest,sud,sud-ouest'],
            ],
        ];
    }

    public function requiredDocuments(): array
    {
        return [
            'id_card' => ['en' => 'National ID card or passport', 'fr' => 'CNI ou passeport'],
            'cv' => ['en' => 'CV', 'fr' => 'CV'],
        ];
    }

    public function phonePrefix(): string
    {
        return '+237';
    }
}
