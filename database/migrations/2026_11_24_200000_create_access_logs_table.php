<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 32)->unique();
            $table->string('client_ip', 45)->index();
            $table->string('method', 10);
            $table->string('uri', 2048);
            $table->string('protocol', 20)->default('HTTP/1.1');
            $table->unsignedSmallInteger('status_code')->index();
            $table->unsignedInteger('body_bytes')->default(0);
            $table->decimal('request_time', 8, 3)->default(0);
            $table->decimal('upstream_response_time', 8, 3)->nullable();
            $table->string('upstream_addr', 100)->nullable();
            $table->string('server_node', 50)->nullable();
            $table->string('referer', 2048)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('geo_label', 100)->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->text('raw_line')->nullable();
            $table->timestamp('logged_at')->index();
            $table->timestamps();
        });

        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
        Schema::dropIfExists('access_logs');
    }
};
