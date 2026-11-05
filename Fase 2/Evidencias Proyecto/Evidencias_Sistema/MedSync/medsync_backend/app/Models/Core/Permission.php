<?php

namespace App\Models\Core;

class Permission extends \Spatie\Permission\Models\Permission
{
    protected $connection = 'core';
}
