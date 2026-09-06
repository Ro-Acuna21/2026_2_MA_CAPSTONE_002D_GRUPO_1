<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $table = 'patient';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'health_insurance_id',
        'medical_center_id',
        'first_name',
        'last_name',
        'rut',
        'birth_date',
        'email',
        'phone',
        'address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'status' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function healthInsurance()
    {
        return $this->belongsTo(HealthInsurance::class, 'health_insurance_id');
    }

    public function medicalCenter()
    {
        return $this->belongsTo(MedicalCenter::class, 'medical_center_id');
    }

    public function centerUser()
    {
        return $this->hasOne(CenterUser::class, 'patient_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }
}