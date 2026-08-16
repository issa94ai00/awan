<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives `users` the phone column the auth controller has always assumed.
 *
 * `AuthController` writes `phone` on every registration and reads it back with
 * `User::where('phone', …)` when someone signs in with a number instead of an
 * address. The column was never created, so both paths hit MySQL error 1054 —
 * registration answered 500 to every request, and phone sign-in could not work
 * at all. That is the field app's normal way in.
 *
 * Unique, not merely indexed. Sign-in resolves the account with `->first()`, so
 * two rows sharing a number would let whichever was created first answer for
 * both — an authentication decision made by insertion order. MySQL permits many
 * NULLs under a unique index, so every existing user keeps an empty phone
 * without colliding.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'phone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'phone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
        });
    }
};
