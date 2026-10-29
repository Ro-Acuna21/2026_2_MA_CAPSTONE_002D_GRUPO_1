<?php

namespace App\Models\Core;

class Role extends \Spatie\Permission\Models\Role
{
    protected $connection = 'core';
}
