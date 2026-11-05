<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

it('keeps services and professional links while removing an empty legacy catalog', function () {
    $path = tempnam(sys_get_temp_dir(), 'specialty-migration-');
    $original = config('database.connections.center');
    try {
        config(['database.connections.center' => array_merge($original, ['driver' => 'sqlite', 'url' => null, 'database' => $path])]);
        DB::purge('center');
        Schema::connection('center')->create('specialties', fn (Blueprint $table) => $table->id());
        Schema::connection('center')->create('services', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('specialty_id');
        });
        Schema::connection('center')->create('professional_specialty', function (Blueprint $table) {
            $table->unsignedBigInteger('professional_id'); $table->unsignedBigInteger('specialty_id');
        });
        DB::connection('center')->table('services')->insert(['id' => 7, 'specialty_id' => 42]);
        DB::connection('center')->table('professional_specialty')->insert(['professional_id' => 9, 'specialty_id' => 42]);

        $migration = require database_path('migrations/center/2026_11_02_000001_remove_obsolete_local_specialty_constraints.php');
        $migration->up();

        expect(Schema::connection('center')->hasTable('specialties'))->toBeFalse();
        expect(DB::connection('center')->table('services')->where('id', 7)->value('specialty_id'))->toBe(42);
        expect(DB::connection('center')->table('professional_specialty')->where('professional_id', 9)->value('specialty_id'))->toBe(42);
    } finally {
        DB::purge('center');
        config(['database.connections.center' => $original]);
        @unlink($path);
    }
});

it('refuses to remove a nonempty legacy catalog', function () {
    $path = tempnam(sys_get_temp_dir(), 'specialty-migration-');
    $original = config('database.connections.center');
    try {
        config(['database.connections.center' => array_merge($original, ['driver' => 'sqlite', 'url' => null, 'database' => $path])]);
        DB::purge('center');
        Schema::connection('center')->create('specialties', fn (Blueprint $table) => $table->id());
        DB::connection('center')->table('specialties')->insert(['id' => 1]);
        $migration = require database_path('migrations/center/2026_11_02_000001_remove_obsolete_local_specialty_constraints.php');
        expect(fn () => $migration->up())->toThrow(RuntimeException::class);
        expect(DB::connection('center')->table('specialties')->count())->toBe(1);
    } finally {
        DB::purge('center');
        config(['database.connections.center' => $original]);
        @unlink($path);
    }
});
