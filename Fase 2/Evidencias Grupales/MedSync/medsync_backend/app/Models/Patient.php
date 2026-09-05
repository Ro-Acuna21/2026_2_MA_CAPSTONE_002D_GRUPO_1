<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $table = 'patient';
    public $timestamps = false;

    protected $fillable = [
        'users_id', 'health_insurance_id', 'medical_center_id',
        'first_name', 'last_name', 'rut', 'birth_date', 'phone',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function healthInsurance()
    {
        return $this->belongsTo(HealthInsurance::class, 'health_insurance_id');
    }

    public function medicalCenter()
    {
        return $this->belongsTo(MedicalCenter::class, 'medical_center_id');
    }
}