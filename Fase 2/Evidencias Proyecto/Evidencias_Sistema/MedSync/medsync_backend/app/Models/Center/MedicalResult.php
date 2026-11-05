<?php

namespace App\Models\Center;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalResult extends Model
{
    use SoftDeletes;

    protected $connection = 'center';

    protected $table = 'medical_results';

    protected $fillable = [
        'patient_id',
        'professional_id',
        'result_type_id',
        'appointment_id',
        'performed_at',
        'status',
        'published_at',
        'created_by',
        'published_by',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'published_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }

    public function resultType(): BelongsTo
    {
        return $this->belongsTo(ResultType::class, 'result_type_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(MedicalResultFile::class, 'medical_result_id');
    }
}
