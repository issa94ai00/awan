<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The record of how an order moved through its stages.
 *
 * Until now the only trace a transition left was a timestamp column on the
 * order itself — `confirmed_at`, `shipped_at`, `delivered_at`. That is three of
 * the five stages, holds no `processing` at all, keeps nothing about who acted
 * or why, and is overwritten if a stage is ever reached twice. So "who cancelled
 * this order, when, and for what reason?" had no answer anywhere in the system.
 *
 * Each row is one move. The table is append-only: entries are never edited or
 * deleted, the same way the ledger keeps a reversal rather than rewriting an
 * entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();

            // Null on the very first row, which records the order being raised.
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);

            // Why the move was made — required in practice for cancellations,
            // optional elsewhere.
            $table->text('note')->nullable();

            // What the move actually did: invoice raised, stock issued, entries
            // posted. Kept so the history explains itself without having to
            // re-derive it from the documents years later.
            $table->json('effects')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // The drawer reads one order's history newest-last; the follow-up
            // view asks how long every order has sat in its current stage.
            $table->index(['sales_order_id', 'id']);
            $table->index(['to_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_status_histories');
    }
};
