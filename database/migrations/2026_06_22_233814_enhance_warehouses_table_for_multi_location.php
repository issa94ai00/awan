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
        Schema::table('warehouses', function (Blueprint $table) {
            $table->enum('location_type', ['warehouse', 'branch', 'distribution_center', '3pl'])->default('warehouse')->after('country');
            $table->decimal('latitude', 10, 8)->nullable()->after('location_type');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->integer('capacity')->nullable()->after('longitude');
            $table->json('operating_hours')->nullable()->after('capacity');
            $table->boolean('is_primary')->default(false)->after('is_active');
            $table->foreignId('manager_id')->nullable()->constrained('users')->after('manager_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['location_type', 'latitude', 'longitude', 'capacity', 'operating_hours', 'is_primary', 'manager_id']);
        });
    }
};
