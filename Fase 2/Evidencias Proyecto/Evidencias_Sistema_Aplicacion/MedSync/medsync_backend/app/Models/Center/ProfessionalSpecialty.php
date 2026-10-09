<?php

namespace App\Models\Center;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProfessionalSpecialty extends Pivot
{
    /**
     * La relación profesional-especialidad pertenece
     * a la base de datos del centro.
     */
    protected $connection = 'center';

    protected $table = 'professional_specialty';

    /**
     * La tabla utiliza una clave primaria compuesta,
     * por lo que no posee un id autoincremental propio.
     */
    public $incrementing = false;

    public $timestamps = true;

    protected $fillable = [
        'professional_id',
        'specialty_id',
    ];

    /**
     * Profesional asociado.
     */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(
            Professional::class,
            'professional_id'
        );
    }

    /**
     * Especialidad asociada.
     */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(
            Specialty::class,
            'specialty_id'
        );
    }
}