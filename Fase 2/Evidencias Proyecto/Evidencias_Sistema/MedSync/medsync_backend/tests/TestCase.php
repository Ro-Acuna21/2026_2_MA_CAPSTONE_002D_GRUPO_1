<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @var array<int, string> */
    protected array $connectionsToTransact = ['core', 'center'];
}
