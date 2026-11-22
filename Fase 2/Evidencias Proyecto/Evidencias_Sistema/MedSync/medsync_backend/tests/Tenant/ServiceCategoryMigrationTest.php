<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->categoryMigrationFile = tempnam(sys_get_temp_dir(), 'medsync_category_');
    config(['database.connections.center' => array_merge(config('database.connections.center'), [
        'driver' => 'sqlite', 'url' => null, 'database' => $this->categoryMigrationFile,
    ])]);
    DB::purge('center');
    Schema::connection('center')->create('services', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    DB::connection('center')->table('services')->insert(['id' => 1, 'name' => 'Prestación antigua']);
});

afterEach(function () {
    DB::purge('center');
    if (isset($this->categoryMigrationFile) && file_exists($this->categoryMigrationFile)) {
        unlink($this->categoryMigrationFile);
    }
});

it('adds a nullable category for legacy rows without rewriting their data', function () {
    $migration = require database_path('migrations/center/2026_11_22_000001_add_category_to_services.php');
    $migration->up();

    expect(Schema::connection('center')->hasColumn('services', 'category'))->toBeTrue()
        ->and(DB::connection('center')->table('services')->where('id', 1)->value('name'))->toBe('Prestación antigua')
        ->and(DB::connection('center')->table('services')->where('id', 1)->value('category'))->toBeNull();
});

it('refuses rollback once categories contain center data', function () {
    $migration = require database_path('migrations/center/2026_11_22_000001_add_category_to_services.php');
    $migration->up();
    DB::connection('center')->table('services')->where('id', 1)->update(['category' => 'CONSULTATION']);

    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'No se puede retirar la categoría: hay prestaciones clasificadas.');
});
