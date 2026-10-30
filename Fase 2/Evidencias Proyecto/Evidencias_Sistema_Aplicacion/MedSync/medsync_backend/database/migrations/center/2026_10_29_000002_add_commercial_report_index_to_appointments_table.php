<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('center')->table('appointments', function (Blueprint $table) {
            $table->index(
                ['appointment_date', 'status'],
                'appointments_report_date_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::connection('center')->table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_report_date_status_index');
        });
    }
};
