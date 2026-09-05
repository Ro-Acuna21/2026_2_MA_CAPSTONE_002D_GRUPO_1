<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CenterUser extends Model
{
    protected $table = 'center_users';
    public $timestamps = false;

    protected $fillable = [
        'medical_center_id', 'user_id', 'role', 'patient_id', 'professional_id', 'status',
    ];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function medicalCenter()
    {
        return $this->belongsTo(MedicalCenter::class, 'medical_center_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}