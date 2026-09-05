<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $table = 'appointment';

    public $timestamps = false;

    protected $fillable = [
        'medical_center_id',
        'patient_id',
        'professional_id',
        'specialty_id',
        'appointment_date',
        'start_time',
        'end_time',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
        ];
    }

    public function medicalCenter()
    {
        return $this->belongsTo(MedicalCenter::class, 'medical_center_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function professional()
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class, 'specialty_id');
    }
}