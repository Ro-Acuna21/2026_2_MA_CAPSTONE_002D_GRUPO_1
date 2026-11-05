<?php

namespace App\Models\Center;

use App\Models\Core\CenterUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'description',
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

/** IDs globales almacenados en el pivote operacional de este centro. */
public function specialtyAssignments(): HasMany
{
    return $this->hasMany(ProfessionalSpecialty::class, 'professional_id');
}
/**
 * Bloques de disponibilidad configurados para el profesional.
 */
public function availabilities(): HasMany
{
    return $this->hasMany(
        Availability::class,
        'professional_id'
    );
}
    /**
 * Reservas asignadas al profesional.
 */
public function appointments(): HasMany
{
    return $this->hasMany(
        Appointment::class,
        'professional_id'
    );
}

    public function medicalResults(): HasMany
    {
        return $this->hasMany(MedicalResult::class, 'professional_id');
    }
}
