<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('center')->hasColumn('patient_addresses', 'postal_code')) {
            Schema::connection('center')->table('patient_addresses', function (Blueprint $table) {
                $table->dropColumn('postal_code');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::connection('center')->hasColumn('patient_addresses', 'postal_code')) {
            Schema::connection('center')->table('patient_addresses', function (Blueprint $table) {
                $table->string('postal_code', 20)->nullable();
            });
        }
    }
};
