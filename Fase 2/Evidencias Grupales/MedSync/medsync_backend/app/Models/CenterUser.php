<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CenterUser extends Model
{
    protected $table = 'center_users';

    protected $fillable = [
        'medical_center_id',
        'user_id',
        'role',
        'patient_id',
        'professional_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function medicalCenter()
    {
        return $this->belongsTo(MedicalCenter::class, 'medical_center_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function professional()
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }
}
