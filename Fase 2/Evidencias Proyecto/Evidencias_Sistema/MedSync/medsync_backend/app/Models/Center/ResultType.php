<?php

namespace App\Models\Center;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResultType extends Model
{
    protected $connection = 'center';

    protected $table = 'result_types';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function medicalResults(): HasMany
    {
        return $this->hasMany(MedicalResult::class, 'result_type_id');
    }
}
