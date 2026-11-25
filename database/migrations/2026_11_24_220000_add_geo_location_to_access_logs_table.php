<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->string('country', 100)->nullable()->after('geo_label');
            $table->string('region', 100)->nullable()->after('country');
            $table->string('city', 100)->nullable()->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropColumn(['country', 'region', 'city']);
        });
    }
};
