<?php

namespace App\Services\Specialties;

use App\Models\Core\MedicalCenterSpecialty;
use App\Models\Core\Specialty;
use App\Support\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Reads and validates global specialty selections for the resolved center.
 * Local service and professional tables store Core specialty IDs after the
 * specialty migration; no cross-database foreign keys are possible.
 */
class CenterSpecialtyCatalog
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    /** All specialties selected by this center, including inactive ones. */
    public function selected(): Collection
    {
        $centerId = $this->tenantContext->medicalCenter()->id;
        $selections = MedicalCenterSpecialty::query()
            ->where('medical_center_id', $centerId)
            ->get()
            ->keyBy('specialty_id');

        return Specialty::query()
            ->whereIn('id', $selections->keys())
            ->orderBy('name')
            ->get()
            ->map(function (Specialty $specialty) use ($selections) {
                $selection = $selections->get($specialty->id);
                $specialty->setAttribute('center_is_active', (bool) ($selection?->is_active ?? false));

                return $specialty;
            });
    }

    /** IDs globally active and explicitly enabled by this center. */
    public function activeIds(): Collection
    {
        $centerId = $this->tenantContext->medicalCenter()->id;
        $selectedIds = MedicalCenterSpecialty::query()
            ->where('medical_center_id', $centerId)
            ->where('is_active', true)
            ->pluck('specialty_id');

        if ($selectedIds->isEmpty()) {
            return collect();
        }

        return Specialty::query()
            ->whereIn('id', $selectedIds)
            ->where('is_active', true)
            ->pluck('id');
    }

    public function isAvailable(int $specialtyId): bool
    {
        $centerId = $this->tenantContext->medicalCenter()->id;

        return Specialty::query()
            ->whereKey($specialtyId)
            ->where('is_active', true)
            ->whereHas('centerSelections', fn ($query) => $query
                ->where('medical_center_id', $centerId)
                ->where('is_active', true))
            ->exists();
    }

    /**
     * Reject new assignments unless the specialty is active globally and for
     * this center. Existing assignments may be retained while editing other
     * fields after an administrator disables the specialty.
     *
     * @param array<int, int|string> $specialtyIds
     * @param array<int, int|string> $existingIds
     */
    public function validateAssignments(array $specialtyIds, array $existingIds = []): void
    {
        $existing = collect($existingIds)->map(fn ($id) => (int) $id)->all();

        foreach (array_unique(array_map('intval', $specialtyIds)) as $specialtyId) {
            if ($this->isAvailable($specialtyId) || in_array($specialtyId, $existing, true)) {
                continue;
            }

            throw ValidationException::withMessages([
                'specialty_ids' => ['Selecciona una especialidad activa en este centro.'],
            ]);
        }
    }

    public function ensureAvailable(int $specialtyId, ?int $existingId = null): void
    {
        if ($this->isAvailable($specialtyId) || $specialtyId === $existingId) {
            return;
        }

        throw ValidationException::withMessages([
            'specialty_id' => ['Selecciona una especialidad activa en este centro.'],
        ]);
    }
}
