<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

/**
 * Selección de un centro dentro del catálogo global de especialidades
 * (medsync_core.medical_center_specialties).
 *
 * Una fila por (medical_center_id, specialty_id). is_active es el estado
 * de activación propio del centro; no afecta el catálogo global ni la
 * selección de otros centros.
 */
class MedicalCenterSpecialty extends Model
{
    protected $connection = 'core';

    protected $table = 'medical_center_specialties';

    protected $fillable = [
        'medical_center_id',
        'specialty_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function medicalCenter()
    {
        return $this->belongsTo(MedicalCenter::class, 'medical_center_id');
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class, 'specialty_id');
    }
}
