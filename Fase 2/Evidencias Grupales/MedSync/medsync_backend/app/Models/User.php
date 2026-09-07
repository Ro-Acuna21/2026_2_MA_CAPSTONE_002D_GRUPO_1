<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
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

    public function patients()
    {
        return $this->hasMany(Patient::class, 'user_id');
    }

    public function professionals()
    {
        return $this->hasMany(Professional::class, 'user_id');
    }

    public function primaryCenterUser()
    {
        return $this->centerUsers()->where('is_active', true)->first();
    }
}
