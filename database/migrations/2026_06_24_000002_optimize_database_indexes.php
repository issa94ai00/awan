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
        // 1. Drop redundant indexes
        Schema::table('site_visitors', function (Blueprint $table) {
            $table->dropIndex(['ip_address']);
        });

        Schema::table('visitors', function (Blueprint $table) {
            $table->dropIndex(['ip_address']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['entity_type']);
        });

        // 2. Add missing indexes
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->index('external_order_id');
        });

        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->index('entity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->dropIndex(['entity_id']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex(['external_order_id']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('entity_type');
        });

        Schema::table('visitors', function (Blueprint $table) {
            $table->index('ip_address');
        });

        Schema::table('site_visitors', function (Blueprint $table) {
            $table->index('ip_address');
        });
    }
};
