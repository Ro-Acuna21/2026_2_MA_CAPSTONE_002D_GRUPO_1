<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('core')->table('center_users', function (Blueprint $table) {
            $table->boolean('invitation_pending')->default(false);
            $table->timestamp('invitation_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('core')->table('center_users', function (Blueprint $table) {
            $table->dropColumn(['invitation_pending', 'invitation_sent_at']);
        });
    }
};
