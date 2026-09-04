<?php

namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    protected $table = 'users';
    public $timestamps = false;
    protected $fillable = ['email', 'password', 'role', 'status'];
    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    public function patient()
    {
        return $this->hasOne(Patient::class, 'users_id');
    }
}