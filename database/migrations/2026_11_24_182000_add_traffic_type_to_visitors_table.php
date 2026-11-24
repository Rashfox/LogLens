<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->string('traffic_type', 20)->default('organic')->after('is_scanner');
            $table->string('bot_name', 100)->nullable()->after('traffic_type');
        });
    }

    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropColumn(['traffic_type', 'bot_name']);
        });
    }
};
