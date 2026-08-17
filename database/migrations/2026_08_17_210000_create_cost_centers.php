<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which part of the business a figure belongs to.
 *
 * The income statement answers whether the company made money. It cannot
 * answer which branch made it, and that is usually the more useful question:
 * a company can be profitable overall while one of its locations quietly loses
 * money every month, and nothing in a combined statement will ever say so.
 *
 * The dimension chosen here is the **warehouse or branch**, because it is the
 * only division this system already knows. Stock is held per warehouse, sales
 * are routed and costed per warehouse, inventory has an account per warehouse,
 * and employees are linked to one. So most postings can name their centre
 * without anybody entering anything — which is what decides whether a cost
 * dimension survives contact with daily use or is left blank on every document.
 *
 * A centre is nonetheless its own record rather than the warehouse itself.
 * Overheads belong to divisions that hold no stock — administration, a
 * delivery fleet — and pinning the dimension to the warehouse table would make
 * those unrepresentable.
 *
 * Lines with no centre are not an error. Shared costs genuinely belong to no
 * branch, and forcing an attribution invents precision; the reports carry them
 * as an explicit unattributed column instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');

            // The link that lets a posting name its centre by itself. Unique:
            // two centres claiming one warehouse would make the attribution
            // depend on which row was read first.
            $table->foreignId('warehouse_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_entry_lines', 'cost_center_id')) {
                // Nullable on delete rather than restricting: removing a centre
                // should cost the analysis, never the entry.
                $table->foreignId('cost_center_id')->nullable()->after('account_id')
                    ->constrained()->nullOnDelete();
                $table->index('cost_center_id');
            }
        });

        // One centre per existing warehouse, so the dimension is populated the
        // moment it exists rather than waiting for somebody to define it.
        $now = now();

        foreach (DB::table('warehouses')->orderBy('id')->get() as $warehouse) {
            if (DB::table('cost_centers')->where('warehouse_id', $warehouse->id)->exists()) {
                continue;
            }

            DB::table('cost_centers')->insert([
                'code' => 'CC-'.str_pad((string) $warehouse->id, 3, '0', STR_PAD_LEFT),
                'name' => $warehouse->name,
                'warehouse_id' => $warehouse->id,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            if (Schema::hasColumn('journal_entry_lines', 'cost_center_id')) {
                $table->dropConstrainedForeignId('cost_center_id');
            }
        });

        Schema::dropIfExists('cost_centers');
    }
};
