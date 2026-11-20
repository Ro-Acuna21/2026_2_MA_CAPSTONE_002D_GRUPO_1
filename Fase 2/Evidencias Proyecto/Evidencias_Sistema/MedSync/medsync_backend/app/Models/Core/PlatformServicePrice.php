<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class PlatformServicePrice extends Model
{
    protected $connection = 'core';
    protected $fillable = ['platform_service_id', 'amount_clp', 'effective_from', 'effective_to'];
    protected function casts(): array { return ['amount_clp' => 'integer', 'effective_from' => 'date', 'effective_to' => 'date']; }
}
