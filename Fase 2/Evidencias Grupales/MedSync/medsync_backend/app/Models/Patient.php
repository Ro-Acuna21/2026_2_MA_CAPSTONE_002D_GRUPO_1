<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    protected $table = 'patients';

    protected $fillable = [
        'medical_center_id',
        'user_id',
        'health_insurance_id',
        'first_name',
        'last_name',
        'rut',
        'birth_date',
        'email',
        'phone',
        'address',
        'medical_insurance',
        'consent_at',
        'consent_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'consent_at' => 'datetime',
            'is_active' => 'boolean',
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
