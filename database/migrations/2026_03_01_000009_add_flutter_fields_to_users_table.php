<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('email');
            $table->boolean('is_pro')->default(false)->after('avatar');
            $table->string('pro_label')->nullable()->after('is_pro');
            $table->boolean('notifications_enabled')->default(true)->after('pro_label');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'is_pro', 'pro_label', 'notifications_enabled']);
        });
    }
};
