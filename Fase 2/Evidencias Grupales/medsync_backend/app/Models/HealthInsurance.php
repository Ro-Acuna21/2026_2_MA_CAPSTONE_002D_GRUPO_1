<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class HealthInsurance extends Model
{
    protected $table = 'health_insurance';
    public $timestamps = false;
    protected $fillable = ['name', 'type', 'status'];
}