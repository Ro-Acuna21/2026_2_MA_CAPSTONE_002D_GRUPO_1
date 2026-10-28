<?php

namespace App\Models\Core;

use App\Models\Center\Patient;
use App\Models\Center\Professional;
use App\Models\User;
use App\Services\Authorization\CenterRolePermissionSync;
use Illuminate\Database\Eloquent\Model;

class CenterUser extends Model
{
    protected static function booted(): void
    {
        static::saved(function (CenterUser $membership): void {
            if ($membership->wasRecentlyCreated || $membership->wasChanged('role')) {
                app(CenterRolePermissionSync::class)->syncMembership($membership);
            }
        });

        static::deleted(function (CenterUser $membership): void {
            app(CenterRolePermissionSync::class)->removeMembership($membership);
        });
    }

    protected $connection = 'core';

    protected $table = 'center_users';

    protected $fillable = [
        'medical_center_id',
        'user_id',
        'role',
        'patient_id',
        'professional_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function medicalCenter()
    {
        return $this->belongsTo(MedicalCenter::class, 'medical_center_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function professional()
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }
}
