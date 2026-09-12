<?php

namespace App\Models\Center;

use App\Models\Core\CenterUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Professional extends Model
{
    use SoftDeletes;

    protected $connection = 'center';

    protected $table = 'professionals';

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'rut',
        'email',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function centerUser()
    {
        return $this->hasOne(CenterUser::class, 'professional_id');
    }

    public function specialties()
    {
        return $this->belongsToMany(
            Specialty::class,
            'professional_specialty',
            'professional_id',
            'specialty_id'
        );
    }

    public function availabilities()
    {
        return $this->hasMany(Availability::class, 'professional_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'professional_id');
    }
}