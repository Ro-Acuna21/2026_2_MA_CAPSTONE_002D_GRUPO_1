<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalCenter extends Model
{
    protected $table = 'medical_center';
    public $timestamps = false;
    protected $fillable = ['name', 'slug', 'rut', 'address', 'phone', 'email', 'status'];
}