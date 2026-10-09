<?php

namespace App\Models\Center;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Specialty extends Model
{
    /**
     * Las especialidades pertenecen a la base operacional
     * del centro médico actualmente resuelto.
     */
    protected $connection = 'center';

    protected $table = 'specialties';

    /**
     * Campos permitidos para asignación masiva.
     */
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    /**
     * Conversión de tipos realizada por Eloquent.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Profesionales asociados a esta especialidad.
     *
     * Un profesional puede tener varias especialidades y una
     * especialidad puede pertenecer a varios profesionales.
     */
    public function professionals(): BelongsToMany
    {
        return $this->belongsToMany(
            Professional::class,
            'professional_specialty',
            'specialty_id',
            'professional_id'
        )
            ->using(ProfessionalSpecialty::class)
            ->withTimestamps();
    }
    /**
 * Prestaciones asociadas a la especialidad.
 */
public function services(): HasMany
{
    return $this->hasMany(
        Service::class,
        'specialty_id'
    );
}
}