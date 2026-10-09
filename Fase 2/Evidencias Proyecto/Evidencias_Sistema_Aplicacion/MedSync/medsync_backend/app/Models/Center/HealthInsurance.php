<?php

namespace App\Models\Center;

use Illuminate\Database\Eloquent\Model;

class HealthInsurance extends Model
{
    protected $connection = 'center';

    protected $table = 'health_insurances';

    protected $fillable = [
        'name',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function patients()
    {
        return $this->hasMany(Patient::class, 'health_insurance_id');
    }
}