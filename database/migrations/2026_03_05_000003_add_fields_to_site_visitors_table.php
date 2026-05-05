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
        Schema::table('site_visitors', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->index();
            $table->unsignedInteger('visit_count')->default(1);
            $table->text('page_url')->nullable();
            $table->text('user_agent')->nullable();
            $table->date('visit_date')->nullable()->index();
            $table->time('visit_time')->nullable();

            $table->unique(['ip_address', 'visit_date'], 'site_visitors_ip_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_visitors', function (Blueprint $table) {
            $table->dropUnique('site_visitors_ip_date_unique');
            $table->dropIndex(['ip_address']);
            $table->dropIndex(['visit_date']);

            $table->dropColumn([
                'ip_address',
                'visit_count',
                'page_url',
                'user_agent',
                'visit_date',
                'visit_time',
            ]);
        });
    }
};
