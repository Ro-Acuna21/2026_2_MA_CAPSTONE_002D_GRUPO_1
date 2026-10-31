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
        'plan',
        'subscription_status',
        'subscription_ends_at',
        'provisioning_status',
        'provisioned_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'subscription_ends_at' => 'datetime',
            'provisioned_at' => 'datetime',
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

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'medical_center_id')->orderByDesc('started_at');
    }

    public function currentSubscription()
    {
        return $this->hasOne(Subscription::class, 'medical_center_id')->whereNull('ended_at');
    }
}
