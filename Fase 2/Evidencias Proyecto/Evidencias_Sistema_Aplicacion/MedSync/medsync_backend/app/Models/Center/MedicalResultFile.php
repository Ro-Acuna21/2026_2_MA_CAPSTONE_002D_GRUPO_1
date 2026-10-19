<?php

namespace App\Models\Center;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalResultFile extends Model
{
    protected $connection = 'center';

    protected $table = 'medical_result_files';

    protected $fillable = [
        'medical_result_id',
        'disk',
        'storage_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'checksum',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function medicalResult(): BelongsTo
    {
        return $this->belongsTo(MedicalResult::class, 'medical_result_id');
    }
}
