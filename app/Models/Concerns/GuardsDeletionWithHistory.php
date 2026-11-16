<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Refuses to delete a master record that documents still point at.
 *
 * The foreign keys behind these records either cascade (wiping purchase
 * orders, credit notes, commissions, cost layers with them) or set null
 * (orphaning invoices and stock history). Either way the books lose their
 * trail, so the delete is refused with a 422 naming what still uses it.
 *
 * A model lists its blockers as table => [column, Arabic label].
 */
trait GuardsDeletionWithHistory
{
    /** @return array<string, array{0: string, 1: string}> */
    abstract protected static function deletionBlockers(): array;

    protected static function bootGuardsDeletionWithHistory(): void
    {
        static::deleting(function ($model) {
            $found = [];

            foreach (static::deletionBlockers() as $table => [$column, $label]) {
                if (Schema::hasTable($table) && DB::table($table)->where($column, $model->getKey())->exists()) {
                    $found[] = $label;
                }
            }

            if ($found !== []) {
                throw ValidationException::withMessages([
                    'delete' => 'لا يمكن الحذف لوجود مستندات مرتبطة: '.implode('، ', $found).'. عطّل السجل بدلاً من حذفه.',
                ]);
            }
        });
    }
}
