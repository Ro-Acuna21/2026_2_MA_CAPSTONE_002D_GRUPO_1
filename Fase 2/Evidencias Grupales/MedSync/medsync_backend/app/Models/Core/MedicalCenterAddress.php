<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class MedicalCenterAddress extends Model
{
    protected $connection = 'core';

    protected $table = 'medical_center_addresses';

    protected $fillable = [
        'medical_center_id',
        'address_line',
        'commune',
        'region',
        'postal_code',
        'reference',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function medicalCenter()
    {
        return $this->belongsTo(MedicalCenter::class, 'medical_center_id');
    }
}