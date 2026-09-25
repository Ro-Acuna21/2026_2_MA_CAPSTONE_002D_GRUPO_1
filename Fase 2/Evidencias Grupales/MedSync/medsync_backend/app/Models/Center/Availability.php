<?php

namespace App\Models\Center;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Availability extends Model
{
    /**
     * La disponibilidad pertenece a la base operacional
     * del centro médico actualmente resuelto.
     */
    protected $connection = 'center';

    protected $table = 'availabilities';

    protected $fillable = [
        'professional_id',
        'weekday',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Profesional al que pertenece este bloque de disponibilidad.
     */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(
            Professional::class,
            'professional_id'
        );
    }
}