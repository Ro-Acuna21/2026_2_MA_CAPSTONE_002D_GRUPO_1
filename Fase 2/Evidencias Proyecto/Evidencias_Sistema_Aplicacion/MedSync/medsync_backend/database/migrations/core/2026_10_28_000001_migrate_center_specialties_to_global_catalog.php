<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('core')->hasTable('medical_center_specialty_mappings')) {
            Schema::connection('core')->create('medical_center_specialty_mappings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('medical_center_id')->constrained('medical_centers')->cascadeOnDelete();
                $table->unsignedBigInteger('local_specialty_id');
                $table->foreignId('specialty_id')->constrained('specialties')->restrictOnDelete();
                $table->string('local_name', 120);
                $table->text('local_description')->nullable();
                $table->boolean('local_is_active')->default(true);
                $table->timestamps();
                $table->unique(['medical_center_id', 'local_specialty_id'], 'mc_specialty_map_local_unique');
                $table->unique(['medical_center_id', 'specialty_id'], 'mc_specialty_map_global_unique');
            });
        }

        $centers = DB::connection('core')->table('medical_centers')->get(['id', 'database_name']);
        foreach ($centers as $center) {
            $connection = config('database.connections.center');
            $connection['database'] = $center->database_name;
            config(['database.connections.specialty_migration_center' => $connection]);
            DB::purge('specialty_migration_center');
            $tenant = DB::connection('specialty_migration_center');

            if (! Schema::connection('specialty_migration_center')->hasTable('specialties')) {
                continue;
            }
            foreach (['services', 'professional_specialty'] as $table) {
                if (! Schema::connection('specialty_migration_center')->hasTable($table)) {
                    throw new RuntimeException("No se puede migrar {$center->database_name}: falta {$table}.");
                }
            }

            $locals = $tenant->table('specialties')->orderBy('id')->get();
            $duplicates = $locals->groupBy(fn ($row) => Str::lower(trim($row->name)))->filter(fn ($rows) => $rows->count() > 1);
            if ($duplicates->isNotEmpty()) {
                throw new RuntimeException("Hay nombres de especialidad duplicados en {$center->database_name}; resolverlos antes de migrar.");
            }

            $tenant->transaction(function () use ($center, $tenant, $locals) {
                foreach ($locals as $local) {
                    $name = trim($local->name);
                    $global = DB::connection('core')->table('specialties')
                        ->whereRaw('LOWER(BTRIM(name)) = ?', [Str::lower($name)])->first();
                    if (! $global) {
                        $now = now();
                        $globalId = DB::connection('core')->table('specialties')->insertGetId([
                            'name' => $name,
                            'description' => $local->description ?? null,
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $globalActive = true;
                    } else {
                        $globalId = $global->id;
                        $globalActive = (bool) $global->is_active;
                    }

                    $now = now();
                    DB::connection('core')->table('medical_center_specialty_mappings')->updateOrInsert(
                        ['medical_center_id' => $center->id, 'local_specialty_id' => $local->id],
                        ['specialty_id' => $globalId, 'local_name' => $local->name,
                         'local_description' => $local->description ?? null, 'local_is_active' => (bool) $local->is_active,
                         'created_at' => $now, 'updated_at' => $now]
                    );

                    $selection = DB::connection('core')->table('medical_center_specialties')
                        ->where('medical_center_id', $center->id)->where('specialty_id', $globalId)->first();
                    if (! $selection) {
                        DB::connection('core')->table('medical_center_specialties')->insert([
                            'medical_center_id' => $center->id, 'specialty_id' => $globalId,
                            'is_active' => (bool) $local->is_active && $globalActive,
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                    }
                }

                $mapping = DB::connection('core')->table('medical_center_specialty_mappings')
                    ->where('medical_center_id', $center->id)->get()->keyBy('local_specialty_id');
                Schema::connection('specialty_migration_center')->table('services', fn (Blueprint $table) => $table->dropForeign('services_specialty_id_foreign'));
                Schema::connection('specialty_migration_center')->table('professional_specialty', fn (Blueprint $table) => $table->dropForeign('professional_specialty_specialty_fk'));
                $pairs = $mapping->mapWithKeys(fn ($row) => [$row->local_specialty_id => $row->specialty_id])->all();
                $this->remapReferences('specialty_migration_center', $pairs);
                Schema::connection('specialty_migration_center')->drop('specialties');
            });
        }
    }

    public function down(): void
    {
        $centers = DB::connection('core')->table('medical_centers')->get(['id', 'database_name']);
        foreach ($centers as $center) {
            $connection = config('database.connections.center');
            $connection['database'] = $center->database_name;
            config(['database.connections.specialty_migration_center' => $connection]);
            DB::purge('specialty_migration_center');
            if (! Schema::connection('specialty_migration_center')->hasTable('services')
                || Schema::connection('specialty_migration_center')->hasTable('specialties')) {
                continue;
            }

            Schema::connection('specialty_migration_center')->create('specialties', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120)->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
            $mappings = DB::connection('core')->table('medical_center_specialty_mappings')
                ->where('medical_center_id', $center->id)->get();
            foreach ($mappings as $mapping) {
                DB::connection('specialty_migration_center')->table('specialties')->insert([
                    'id' => $mapping->local_specialty_id, 'name' => $mapping->local_name,
                    'description' => $mapping->local_description, 'is_active' => $mapping->local_is_active,
                    'created_at' => $mapping->created_at, 'updated_at' => $mapping->updated_at,
                ]);
            }
            $reversePairs = $mappings->mapWithKeys(fn ($mapping) => [$mapping->specialty_id => $mapping->local_specialty_id])->all();
            $mappedGlobalIds = $mappings->pluck('specialty_id')->all();
            $newSelections = DB::connection('core')->table('medical_center_specialties as mcs')
                ->join('specialties as s', 's.id', '=', 'mcs.specialty_id')
                ->where('mcs.medical_center_id', $center->id)
                ->when($mappedGlobalIds, fn ($query) => $query->whereNotIn('mcs.specialty_id', $mappedGlobalIds))
                ->select('s.id', 's.name', 's.description', 's.is_active')
                ->get();
            $nextLocalId = (int) (DB::connection('specialty_migration_center')->table('specialties')->max('id') ?? 0) + 1;
            foreach ($newSelections as $selection) {
                DB::connection('specialty_migration_center')->table('specialties')->insert([
                    'id' => $nextLocalId, 'name' => $selection->name,
                    'description' => $selection->description, 'is_active' => $selection->is_active,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $reversePairs[$selection->id] = $nextLocalId;
                $nextLocalId++;
            }
            $this->remapReferences('specialty_migration_center', $reversePairs);
            Schema::connection('specialty_migration_center')->table('services', function (Blueprint $table) {
                $table->foreign('specialty_id', 'services_specialty_id_foreign')->references('id')->on('specialties')->restrictOnDelete();
            });
            Schema::connection('specialty_migration_center')->table('professional_specialty', function (Blueprint $table) {
                $table->foreign('specialty_id', 'professional_specialty_specialty_fk')->references('id')->on('specialties')->restrictOnDelete();
            });
            if (DB::connection('specialty_migration_center')->getDriverName() === 'pgsql') {
                DB::connection('specialty_migration_center')->statement("SELECT setval(pg_get_serial_sequence('specialties','id'), COALESCE((SELECT MAX(id) FROM specialties), 1))");
            }
        }
        Schema::connection('core')->dropIfExists('medical_center_specialty_mappings');
    }

    private function remapReferences(string $connection, array $pairs): void
    {
        if ($pairs === []) {
            return;
        }
        $temporaryPairs = [];
        $temporaryId = -1;
        foreach ($pairs as $from => $to) {
            $temporaryPairs[(int) $from] = $temporaryId--;
        }
        $finalPairs = [];
        foreach ($pairs as $from => $to) {
            $finalPairs[$temporaryPairs[(int) $from]] = (int) $to;
        }
        foreach (['services', 'professional_specialty'] as $table) {
            $this->updateReferenceTable($connection, $table, $temporaryPairs);
            $this->updateReferenceTable($connection, $table, $finalPairs);
        }
    }

    private function updateReferenceTable(string $connection, string $table, array $pairs): void
    {
        if ($pairs === []) {
            return;
        }
        $case = [];
        $bindings = [];
        foreach ($pairs as $from => $to) {
            $case[] = 'WHEN ? THEN ?';
            $bindings[] = (int) $from;
            $bindings[] = (int) $to;
        }
        $ids = array_map('intval', array_keys($pairs));
        $in = implode(', ', array_fill(0, count($ids), '?'));
        $bindings = [...$bindings, ...$ids];
        DB::connection($connection)->update(
            "UPDATE {$table} SET specialty_id = CASE specialty_id ".implode(' ', $case)." ELSE specialty_id END WHERE specialty_id IN ({$in})",
            $bindings
        );
    }
};
