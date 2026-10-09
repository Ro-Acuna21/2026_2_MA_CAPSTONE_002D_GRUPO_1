<?php

namespace App\Models\Center;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentHistory extends Model
{
    protected $connection = 'center';

    protected $table = 'appointment_history';

    /**
     * Los eventos poseen created_at, pero no updated_at,
     * ya que el historial debe ser inmutable.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'appointment_id',
        'actor_user_id',
        'event_type',

        'previous_status',
        'new_status',

        'old_date',
        'old_start_time',
        'old_end_time',
        'new_date',
        'new_start_time',
        'new_end_time',

        'old_professional_id',
        'new_professional_id',

        'old_service_id',
        'new_service_id',

        'reason',
    ];

    protected function casts(): array
    {
        return [
            'old_date' => 'date',
            'new_date' => 'date',
        ];
    }

    /**
     * Reserva asociada al evento histórico.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(
            Appointment::class,
            'appointment_id'
        );
    }
}