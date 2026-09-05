<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthInsurance extends Model
{
    protected $table = 'health_insurance';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function patients()
    {
        return $this->hasMany(Patient::class, 'health_insurance_id');
    }
}