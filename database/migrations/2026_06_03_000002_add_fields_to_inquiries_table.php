<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            // Add user_id foreign key
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            
            // Add priority field
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->after('status');
            
            // Add assignment field
            $table->foreignId('assigned_to')->nullable()->after('priority')->constrained('users')->nullOnDelete();
            
            // Add closure tracking
            $table->timestamp('closed_at')->nullable()->after('user_agent');
            
            // Add soft deletes
            $table->softDeletes();
            
            // Add indexes for better query performance
            $table->index('status');
            $table->index('priority');
            $table->index('assigned_to');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['user_id', 'priority', 'assigned_to', 'closed_at']);
            $table->dropSoftDeletes();
            $table->dropIndex(['status']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['created_at']);
        });
    }
};
