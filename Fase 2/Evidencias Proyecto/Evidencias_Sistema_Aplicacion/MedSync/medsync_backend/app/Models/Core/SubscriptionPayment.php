<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    protected $connection = 'core';
    protected $fillable = ['subscription_id', 'amount_clp', 'status', 'due_at', 'paid_at', 'provider_reference', 'recorded_by'];
    protected function casts(): array
    {
        return ['amount_clp' => 'integer', 'due_at' => 'datetime', 'paid_at' => 'datetime'];
    }
}
