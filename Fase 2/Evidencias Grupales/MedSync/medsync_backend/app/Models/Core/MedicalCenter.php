<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class MedicalCenter extends Model
{
    protected $connection = 'core';

    protected $table = 'medical_centers';

    protected $fillable = [
        'name',
        'slug',
        'database_name',
        'rut',
        'address',
        'phone',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function centerUsers()
    {
        return $this->hasMany(CenterUser::class, 'medical_center_id');
    }
}