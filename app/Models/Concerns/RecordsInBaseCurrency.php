<?php

namespace App\Models\Concerns;

/**
 * A financial document is denominated in the books' own currency unless it
 * says otherwise.
 *
 * The `currency` column on invoices, payments, sales orders and expenses was
 * created with `DEFAULT 'SAR'` — a literal from the application's original
 * Saudi context — and several creation paths never set the field at all. So
 * on books whose base currency is something else, documents were stamped with
 * a currency nobody had configured, purely by the schema's say-so. Nothing
 * converts at posting time, so the amounts still went to the ledger at face
 * value: the figures were right and only their label was wrong, which is the
 * most expensive kind of wrong to find later.
 *
 * Filling the field here rather than moving the column default to another
 * literal means it follows configuration. Change the base currency and the
 * next document follows it, instead of inheriting whatever was true the day
 * the table was built.
 *
 * An explicitly supplied currency is always left alone — genuine foreign
 * documents are a real thing, and this is a default, not a rule.
 */
trait RecordsInBaseCurrency
{
    protected static function bootRecordsInBaseCurrency(): void
    {
        static::creating(function ($model) {
            if (blank($model->currency)) {
                $model->currency = base_currency_code();
            }

            // The books are kept in one currency and nothing is converted on
            // the way in, so a document in the base currency is 1:1 by
            // definition. Only a real foreign document earns another rate.
            if (in_array('exchange_rate', $model->getFillable(), true) && blank($model->exchange_rate)) {
                $model->exchange_rate = 1.0;
            }
        });
    }
}
