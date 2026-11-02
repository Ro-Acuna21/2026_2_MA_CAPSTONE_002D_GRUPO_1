<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class CommercialInquiry extends Model
{
    protected $connection = 'core';

    protected $fillable = ['plan_id', 'contact_name', 'center_name', 'email', 'phone', 'message'];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
