<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * permission_role and role_user were the hand-rolled role pivots from before
 * 2026_10_14_150000_adopt_spatie_permission. Spatie's role_has_permissions
 * and model_has_roles replaced them; nothing in app/, routes/, config/,
 * seeders or the front end reads them any more. Their rows are written to
 * storage/app/private/backups (the local disk) before the drop, and down()
 * restores them.
 */
return new class extends Migration
{
    private const TABLES = ['permission_role', 'role_user'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Storage::disk('local')->put(
                "backups/legacy-{$table}.json",
                DB::table($table)->orderBy('id')->get()->toJson(JSON_PRETTY_PRINT)
            );

            Schema::drop($table);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $table) {
                $table->id();
                $table->foreignId('permission_id')->constrained()->onDelete('cascade');
                $table->foreignId('role_id')->constrained()->onDelete('cascade');
                $table->timestamps();
                $table->unique(['permission_id', 'role_id']);
            });
        }

        if (! Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->timestamps();
                $table->unique(['role_id', 'user_id']);
            });
        }

        foreach (self::TABLES as $table) {
            $path = "backups/legacy-{$table}.json";
            if (Storage::disk('local')->exists($path)) {
                $rows = json_decode(Storage::disk('local')->get($path), true) ?: [];
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        }
    }
};
