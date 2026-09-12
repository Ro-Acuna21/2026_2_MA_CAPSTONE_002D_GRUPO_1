<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('core')->table('users', function (Blueprint $table) {
            $table->string('system_role', 50)->nullable()->after('password');
            $table->index('system_role');
        });
    }

    public function down(): void
    {
        Schema::connection('core')->table('users', function (Blueprint $table) {
            $table->dropIndex(['system_role']);
            $table->dropColumn('system_role');
        });
    }
};