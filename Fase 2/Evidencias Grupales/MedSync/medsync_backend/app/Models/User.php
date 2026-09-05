<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'boolean',
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
        return $this->centerUsers()
            ->where('status', true)
            ->first();
    }
}