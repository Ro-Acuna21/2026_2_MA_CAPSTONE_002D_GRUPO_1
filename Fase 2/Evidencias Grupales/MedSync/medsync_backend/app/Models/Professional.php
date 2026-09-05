<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Professional extends Model
{
    protected $table = 'professional';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'medical_center_id',
        'first_name',
        'last_name',
        'rut',
        'email',
        'phone',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function medicalCenter()
    {
        return $this->belongsTo(MedicalCenter::class, 'medical_center_id');
    }

    public function centerUser()
    {
        return $this->hasOne(CenterUser::class, 'professional_id');
    }

    public function specialties()
    {
        return $this->belongsToMany(
            Specialty::class,
            'professional_specialty',
            'professional_id',
            'specialty_id'
        );
    }

    public function availabilities()
    {
        return $this->hasMany(Availability::class, 'professional_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'professional_id');
    }
}