<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';
    public $timestamps = false;

    protected $fillable = ['email', 'password', 'status'];
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

    public function centerUsers()
    {
        return $this->hasMany(CenterUser::class, 'user_id');
    }

    /**
     * Mientras cada usuario pertenezca a un solo centro (caso actual),
     * este helper simplifica la vida del AuthController.
     */
    public function primaryCenterUser()
    {
        return $this->centerUsers()->where('status', true)->first();
    }
}