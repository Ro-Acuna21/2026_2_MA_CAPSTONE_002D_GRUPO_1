<?php

namespace App\Models\Center;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientAddress extends Model
{
    use SoftDeletes;

    protected $connection = 'center';

    protected $table = 'patient_addresses';

    protected $fillable = [
        'patient_id',
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

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}