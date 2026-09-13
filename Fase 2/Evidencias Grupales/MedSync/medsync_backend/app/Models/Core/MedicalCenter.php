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
    public function addresses()
{
    return $this->hasMany(MedicalCenterAddress::class, 'medical_center_id');
}
public function primaryAddress()
{
    return $this->hasOne(MedicalCenterAddress::class, 'medical_center_id')
        ->where('is_primary', true);
}

    public function centerUsers()
    {
        return $this->hasMany(CenterUser::class, 'medical_center_id');
    }
}