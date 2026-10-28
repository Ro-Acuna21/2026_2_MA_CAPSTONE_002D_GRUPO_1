<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class TerritorialCatalog
{
    /** @var array<string, mixed>|null */
    private ?array $catalog = null;

    /** @return array<int, array<string, mixed>> */
    public function regions(): array
    {
        return $this->load()['regions'];
    }

    /**
     * Resolve CUT codes to canonical names and ensure the commune belongs to
     * the selected region. Null codes represent an omitted address section.
     *
     * @return array{region: string, commune: string}|null
     */
    public function resolve(?string $regionCode, ?string $communeCode): ?array
    {
        $regionCode = trim((string) $regionCode);
        $communeCode = trim((string) $communeCode);

        if ($regionCode === '' && $communeCode === '') {
            return null;
        }

        if ($regionCode === '') {
            throw ValidationException::withMessages([
                'region_code' => ['Selecciona una región para validar la comuna.'],
            ]);
        }

        if ($communeCode === '') {
            throw ValidationException::withMessages([
                'commune_code' => ['Selecciona una comuna.'],
            ]);
        }

        foreach ($this->regions() as $region) {
            if ((string) $region['code'] !== $regionCode) {
                continue;
            }

            foreach ($region['communes'] as $commune) {
                if ((string) $commune['code'] === $communeCode) {
                    return [
                        'region' => $region['name'],
                        'commune' => $commune['name'],
                    ];
                }
            }

            break;
        }

        throw ValidationException::withMessages([
            'commune_code' => ['La comuna seleccionada no pertenece a la región indicada.'],
        ]);
    }

    /** @return array<string, mixed> */
    private function load(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $path = resource_path('data/chile-territorial-catalog.json');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException('No se pudo leer el catálogo territorial de SUBDERE.');
        }

        $catalog = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! isset($catalog['regions']) || ! is_array($catalog['regions'])) {
            throw new \RuntimeException('El catálogo territorial de SUBDERE no tiene un formato válido.');
        }

        return $this->catalog = $catalog;
    }
}
