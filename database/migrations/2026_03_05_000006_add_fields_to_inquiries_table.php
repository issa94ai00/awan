<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('email')->after('name');
            $table->string('phone')->nullable()->after('email');
            $table->string('subject')->after('phone');
            $table->text('message')->after('subject');
            $table->enum('status', ['new', 'read', 'replied'])->default('new')->after('message');
            $table->foreignId('product_id')->nullable()->after('status')->constrained('products')->nullOnDelete();
            $table->string('ip_address', 45)->nullable()->after('product_id');
            $table->text('user_agent')->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn(['name', 'email', 'phone', 'subject', 'message', 'status', 'product_id', 'ip_address', 'user_agent']);
        });
    }
};
