<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $connection = 'core';
    protected $fillable = ['medical_center_id', 'plan_id', 'status', 'started_at', 'renews_at', 'ended_at', 'notes', 'monthly_price_clp_snapshot'];
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'renews_at' => 'datetime', 'ended_at' => 'datetime', 'monthly_price_clp_snapshot' => 'integer'];
    }
    public function plan() { return $this->belongsTo(Plan::class); }
    public function payments() { return $this->hasMany(SubscriptionPayment::class)->orderByDesc('due_at'); }
}
