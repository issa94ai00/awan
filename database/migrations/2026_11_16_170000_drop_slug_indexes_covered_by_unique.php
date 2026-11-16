<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The UNIQUE slug indexes added in 2026_11_16_160000 cover these. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'categories'] as $table) {
            if (Schema::hasIndex($table, "{$table}_slug_index") && Schema::hasIndex($table, "{$table}_slug_unique")) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex("{$table}_slug_index"));
            }
        }
    }

    public function down(): void
    {
        foreach (['products', 'categories'] as $table) {
            if (! Schema::hasIndex($table, "{$table}_slug_index")) {
                Schema::table($table, fn (Blueprint $t) => $t->index('slug', "{$table}_slug_index"));
            }
        }
    }
};
