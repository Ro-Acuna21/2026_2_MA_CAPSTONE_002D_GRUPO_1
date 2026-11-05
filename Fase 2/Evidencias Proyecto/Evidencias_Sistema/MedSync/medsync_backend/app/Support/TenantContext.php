<?php

namespace App\Support;

use App\Models\Core\CenterUser;
use App\Models\Core\MedicalCenter;
use LogicException;

/**
 * Tenant clínico resuelto para la vida de una sola petición.
 *
 * Nunca recibe datos desde el cliente: el middleware lo construye a partir
 * de la sesión Sanctum y de las tablas de Core ya validadas.
 */
class TenantContext
{
    private ?MedicalCenter $medicalCenter = null;

    private ?CenterUser $centerUser = null;

    public function set(MedicalCenter $medicalCenter, CenterUser $centerUser): void
    {
        if (
            $centerUser->medical_center_id !== $medicalCenter->id
            || ! $medicalCenter->is_active
            || ! $centerUser->is_active
        ) {
            throw new LogicException('El contexto de centro no es válido.');
        }

        if ($this->medicalCenter && $this->medicalCenter->id !== $medicalCenter->id) {
            throw new LogicException('No se puede cambiar de centro dentro de una petición.');
        }

        $this->medicalCenter = $medicalCenter;
        $this->centerUser = $centerUser;
    }

    /**
     * Reinicia el contexto al iniciar una nueva petición HTTP.
     */
    public function reset(): void
    {
        $this->medicalCenter = null;
        $this->centerUser = null;
    }

    public function medicalCenter(): MedicalCenter
    {
        if (! $this->medicalCenter) {
            throw new LogicException('No existe un centro clínico resuelto para esta petición.');
        }

        return $this->medicalCenter;
    }

    public function centerUser(): CenterUser
    {
        if (! $this->centerUser) {
            throw new LogicException('No existe una membresía de centro resuelta para esta petición.');
        }

        return $this->centerUser;
    }

    public function hasCenter(): bool
    {
        return $this->medicalCenter !== null;
    }
}
