<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo global de especialidades (medsync_core.specialties).
 *
 * Lo administra el SUPER_ADMIN de la plataforma. No confundir con
 * Las referencias guardadas en bases Center son IDs lógicos, validados
 * por la aplicación porque no hay claves foráneas entre bases.
 */
class Specialty extends Model
{
    protected $connection = 'core';

    protected $table = 'specialties';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function centerSelections()
    {
        return $this->hasMany(MedicalCenterSpecialty::class, 'specialty_id');
    }
}
