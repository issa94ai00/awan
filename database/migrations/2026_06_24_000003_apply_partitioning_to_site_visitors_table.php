<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // In MySQL, to drop a primary key on an AUTO_INCREMENT column, we must temporarily remove auto_increment
            DB::statement("ALTER TABLE site_visitors MODIFY id bigint(20) UNSIGNED NOT NULL");
            DB::statement("ALTER TABLE site_visitors DROP PRIMARY KEY");
            DB::statement("ALTER TABLE site_visitors ADD PRIMARY KEY (id, visit_date)");
            DB::statement("ALTER TABLE site_visitors MODIFY id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT");

            // Apply RANGE partitioning on the date of visit
            DB::statement("ALTER TABLE site_visitors PARTITION BY RANGE COLUMNS(visit_date) (
                PARTITION p2026_q1 VALUES LESS THAN ('2026-04-01'),
                PARTITION p2026_q2 VALUES LESS THAN ('2026-07-01'),
                PARTITION p2026_q3 VALUES LESS THAN ('2026-10-01'),
                PARTITION p2026_q4 VALUES LESS THAN ('2027-01-01'),
                PARTITION p_future VALUES LESS THAN (MAXVALUE)
            )");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Remove partitioning
            DB::statement("ALTER TABLE site_visitors REMOVE PARTITIONING");

            // Restore primary key to id only
            DB::statement("ALTER TABLE site_visitors MODIFY id bigint(20) UNSIGNED NOT NULL");
            DB::statement("ALTER TABLE site_visitors DROP PRIMARY KEY");
            DB::statement("ALTER TABLE site_visitors ADD PRIMARY KEY (id)");
            DB::statement("ALTER TABLE site_visitors MODIFY id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT");
        }
    }
};
