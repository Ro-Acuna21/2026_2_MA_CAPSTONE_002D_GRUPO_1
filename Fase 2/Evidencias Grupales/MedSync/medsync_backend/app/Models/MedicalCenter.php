<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalCenter extends Model
{
    protected $table = 'medical_center';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'rut',
        'address',
        'phone',
        'email',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function centerUsers()
    {
        return $this->hasMany(CenterUser::class, 'medical_center_id');
    }

    public function patients()
    {
        return $this->hasMany(Patient::class, 'medical_center_id');
    }

    public function professionals()
    {
        return $this->hasMany(Professional::class, 'medical_center_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'medical_center_id');
    }
}