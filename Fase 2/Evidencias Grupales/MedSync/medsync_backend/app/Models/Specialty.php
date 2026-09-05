<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
    protected $table = 'specialty';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
    ];

    public function professionals()
    {
        return $this->belongsToMany(
            Professional::class,
            'professional_specialty',
            'specialty_id',
            'professional_id'
        );
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'specialty_id');
    }
}