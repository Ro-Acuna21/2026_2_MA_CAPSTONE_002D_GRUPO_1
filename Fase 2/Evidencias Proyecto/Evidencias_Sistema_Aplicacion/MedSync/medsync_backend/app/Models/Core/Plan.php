<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $connection = 'core';
    protected $fillable = ['name', 'code', 'description', 'monthly_price_clp', 'professional_limit', 'user_limit', 'features', 'is_active'];
    protected function casts(): array
    {
        return ['monthly_price_clp' => 'integer', 'professional_limit' => 'integer', 'user_limit' => 'integer', 'features' => 'array', 'is_active' => 'boolean'];
    }
}
