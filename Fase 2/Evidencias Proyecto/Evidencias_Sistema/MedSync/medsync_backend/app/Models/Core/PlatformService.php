<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class PlatformService extends Model
{
    protected $connection = 'core';
    protected $fillable = ['name', 'category', 'duration_minutes', 'is_active'];
    protected function casts(): array { return ['duration_minutes' => 'integer', 'is_active' => 'boolean']; }
}
