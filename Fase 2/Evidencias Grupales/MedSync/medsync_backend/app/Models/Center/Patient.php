<?php

namespace App\Models\Center;

use App\Models\Core\CenterUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    protected $connection = 'center';

    protected $table = 'patients';

    protected $fillable = [
        'user_id',
        'health_insurance_id',
        'first_name',
        'last_name',
        'rut',
        'birth_date',
        'email',
        'phone',
        'address',
        'medical_insurance',
        'consent_at',
        'consent_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'consent_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function healthInsurance()
    {
        return $this->belongsTo(HealthInsurance::class, 'health_insurance_id');
    }

    public function centerUser()
    {
        return $this->hasOne(CenterUser::class, 'patient_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }
}