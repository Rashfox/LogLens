<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->string('method', 20)->nullable()->after('url');
            $table->unsignedSmallInteger('status_code')->nullable()->after('method');
            $table->boolean('is_scanner')->default(false)->after('status_code');
            $table->string('scanner_name', 100)->nullable()->after('is_scanner');
        });
    }

    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            $table->dropColumn(['method', 'status_code', 'is_scanner', 'scanner_name']);
        });
    }
};
