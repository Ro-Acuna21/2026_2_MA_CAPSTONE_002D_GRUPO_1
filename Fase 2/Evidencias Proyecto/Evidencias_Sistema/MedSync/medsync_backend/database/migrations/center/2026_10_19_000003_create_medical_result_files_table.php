<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('center')->create('medical_result_files', function (Blueprint $table) {
            $table->id();

            $table->foreignId('medical_result_id')
                ->constrained('medical_results')
                ->restrictOnDelete();

            $table->string('disk', 50);
            $table->string('storage_path', 1024);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64)->nullable();
            $table->bigInteger('uploaded_by');
            $table->timestamps();

            $table->index('medical_result_id', 'medical_result_files_result_index');
        });

        if (DB::connection('center')->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection('center')->statement(
            "ALTER TABLE medical_result_files
            ADD CONSTRAINT medical_result_files_size_positive
            CHECK (size_bytes > 0)"
        );
    }

    public function down(): void
    {
        Schema::connection('center')->dropIfExists('medical_result_files');
    }
};
