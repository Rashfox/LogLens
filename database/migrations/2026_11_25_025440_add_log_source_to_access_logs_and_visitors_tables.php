<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('access_logs')) {
            Schema::table('access_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('access_logs', 'log_source')) {
                    $table->string('log_source', 32)
                        ->default('access_log')
                        ->after('request_id');
                }

                // Index untuk mempercepat filter berdasarkan sumber log, node, dan waktu
                $table->index(['log_source', 'server_node', 'logged_at'], 'idx_access_logs_source_node_time');
            });
        }

        if (Schema::hasTable('visitors')) {
            Schema::table('visitors', function (Blueprint $table) {
                if (!Schema::hasColumn('visitors', 'log_source')) {
                    $table->string('log_source', 32)
                        ->default('access_log')
                        ->after('id');
                }

                $table->index(['log_source', 'updated_at'], 'idx_visitors_source_updated');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('access_logs')) {
            Schema::table('access_logs', function (Blueprint $table) {
                $table->dropIndex('idx_access_logs_source_node_time');
                if (Schema::hasColumn('access_logs', 'log_source')) {
                    $table->dropColumn('log_source');
                }
            });
        }

        if (Schema::hasTable('visitors')) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->dropIndex('idx_visitors_source_updated');
                if (Schema::hasColumn('visitors', 'log_source')) {
                    $table->dropColumn('log_source');
                }
            });
        }
    }
};
