<?php

namespace App\Models;

use App\Models\Core\CenterUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes;

    protected $connection = 'core';

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'system_role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function centerUsers()
    {
        return $this->hasMany(CenterUser::class, 'user_id');
    }

    public function primaryCenterUser()
    {
        return $this->centerUsers()
            ->where('is_active', true)
            ->first();
    }
    public function isSuperAdmin(): bool {
    return $this->system_role === 'SUPER_ADMIN';
}
}