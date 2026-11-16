<?php

namespace App\Models\Concerns;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

/**
 * One barcode, one sellable thing.
 *
 * The POS and the scanners look a barcode up across products, their variants
 * and their packaging units, so a code shared by two of them sells whichever
 * the lookup happens to find first. Only ProductVariantController checked
 * this, and only for variants; every save path now goes through here.
 *
 * Blank barcodes are stored as NULL so the UNIQUE indexes do not treat every
 * empty string as the same code.
 */
trait HasUniqueBarcode
{
    protected static function bootHasUniqueBarcode(): void
    {
        static::saving(function ($model) {
            $code = is_string($model->barcode) ? trim($model->barcode) : $model->barcode;
            $model->barcode = $code === '' ? null : $code;

            if ($model->barcode === null || ! $model->isDirty('barcode')) {
                return;
            }

            foreach ([Product::class, ProductVariant::class, ProductUnit::class] as $class) {
                $query = $class::query()->where('barcode', $model->barcode);

                if ($class === $model::class && $model->exists) {
                    $query->whereKeyNot($model->getKey());
                }

                if ($query->exists()) {
                    throw ValidationException::withMessages([
                        'barcode' => "الباركود {$model->barcode} مستخدم لصنف آخر.",
                    ]);
                }
            }
        });
    }
}
