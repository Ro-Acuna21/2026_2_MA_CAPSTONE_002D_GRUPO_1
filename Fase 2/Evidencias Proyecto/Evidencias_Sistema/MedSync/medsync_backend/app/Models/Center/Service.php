<?php

namespace App\Models\Center;

use App\Models\Core\Specialty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    /**
     * Las prestaciones pertenecen a la base operacional
     * del centro médico actualmente resuelto.
     */
    protected $connection = 'center';

    protected $table = 'services';

    protected $fillable = [
        'specialty_id',
        'name',
        'description',
        'duration_minutes',
        'is_active',
        'platform_service_id',
        'category',
        'is_shared_exam',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
            'is_shared_exam' => 'boolean',
        ];
    }

    /**
     * Especialidad a la que pertenece la prestación.
     */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(
            Specialty::class,
            'specialty_id'
        );
    }

    /**
     * Reservas realizadas para esta prestación.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(
            Appointment::class,
            'service_id'
        );
    }
}
