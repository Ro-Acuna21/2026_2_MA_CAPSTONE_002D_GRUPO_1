<?php

namespace App\Models\Center;

use Illuminate\Database\Eloquent\Model;

class ServicePrice extends Model
{
    protected $connection = 'center';

    protected $fillable = ['service_id', 'amount_clp', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return ['amount_clp' => 'integer', 'effective_from' => 'date', 'effective_to' => 'date'];
    }
}
