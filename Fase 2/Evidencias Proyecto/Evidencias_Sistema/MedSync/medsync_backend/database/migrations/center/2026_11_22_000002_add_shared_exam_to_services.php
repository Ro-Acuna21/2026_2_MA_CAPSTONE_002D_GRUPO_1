<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('center')->table('services', function (Blueprint $table) {
            $table->boolean('is_shared_exam')->default(false);
        });
    }

    public function down(): void
    {
        if (DB::connection('center')->table('services')->where('is_shared_exam', true)->exists()) {
            throw new RuntimeException('No se puede retirar la configuración de exámenes compartidos mientras existan prestaciones habilitadas.');
        }
        Schema::connection('center')->table('services', fn (Blueprint $table) => $table->dropColumn('is_shared_exam'));
    }
};
