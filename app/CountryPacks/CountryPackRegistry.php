<?php

namespace App\CountryPacks;

use App\CountryPacks\Contracts\CountryPack;
use App\Models\Tenant;
use InvalidArgumentException;

final class CountryPackRegistry
{
    /** @param array<string, class-string<CountryPack>> $packs */
    public function __construct(private readonly array $packs) {}

    public function get(string $code): CountryPack
    {
        $class = $this->packs[strtoupper($code)] ?? throw new InvalidArgumentException("Country pack [{$code}] is not installed.");

        return app($class);
    }

    /** @return list<CountryPack> */
    public function forTenant(Tenant $tenant): array
    {
        return array_values(array_map(
            fn (string $code) => $this->get($code),
            array_filter($tenant->enabledCountryPacks(), fn (string $code) => isset($this->packs[strtoupper($code)])),
        ));
    }

    /** @return list<string> */
    public function installed(): array
    {
        return array_keys($this->packs);
    }
}
