<?php

namespace App\Models\Center;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    /**
     * Las reservas pertenecen a la base operacional
     * del centro actualmente resuelto.
     */
    protected $connection = 'center';

    protected $table = 'appointments';

    protected $fillable = [
        'patient_id',
        'professional_id',
        'service_id',
        'appointment_date',
        'start_time',
        'end_time',
        'status',
        'source',
        'note',
        'overbook',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'overbook' => 'boolean',
        ];
    }

    /**
     * Paciente asociado a la reserva.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(
            Patient::class,
            'patient_id'
        );
    }

    /**
     * Profesional asignado a la reserva.
     */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(
            Professional::class,
            'professional_id'
        );
    }

    /**
     * Prestación reservada.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(
            Service::class,
            'service_id'
        );
    }

    /**
     * Eventos históricos asociados a la reserva.
     */
    public function history(): HasMany
    {
        return $this->hasMany(
            AppointmentHistory::class,
            'appointment_id'
        )->orderBy('created_at');
    }
}