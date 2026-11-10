<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * The server's half of Settings → Document numbering.
 *
 * Every document the system issues — an invoice, a sales order, a purchase
 * receipt, a payment voucher — carries a number such as `INV-000123`. The
 * letters in front used to be typed into each generator by hand, in some
 * forty places. They now come from here, so the person running the books can
 * change `INV` to `فاتورة` or `SI` from the settings page and every generator
 * follows.
 *
 * Only the letters are configurable; the separator and the zero-padded
 * sequence stay as they are, so numbers already issued keep sorting next to
 * the new ones. Documents already numbered are never renamed: a changed
 * prefix applies from the next document onward.
 */
class DocumentNumbers
{
    /** Mirrors DOC_PREFIX_DEFAULTS in resources/js/utils/documentNumbering.js — the two must agree. */
    public const DEFAULTS = [
        // Sales
        'invoice' => 'INV',
        'sales_order' => 'SO',
        'quote' => 'QT',
        'sales_contract' => 'CTR',
        'payment' => 'PAY',
        'refund' => 'REF',
        'credit_note' => 'CN',
        'rma' => 'RMA',
        'storefront_order' => 'ORD',
        // Purchasing
        'purchase_order' => 'PO',
        'purchase_receipt' => 'PR',
        'purchase_return' => 'PRT',
        'supplier_payment' => 'SPY',
        // Inventory & logistics
        'inventory_transfer' => 'TRF',
        'cycle_count' => 'CC',
        'picking_list' => 'PL',
        'packing_list' => 'PCK',
        'shipping_manifest' => 'SHP',
        'production_order' => 'PROD',
        // Finance & HR
        'expense' => 'EXP',
        'journal_entry' => 'JE',
        'fixed_asset' => 'FA',
        'bank_reconciliation' => 'BR',
        'payroll' => 'PAY',
    ];

    public const SETTING_PREFIX = 'doc_prefix_';

    public const SEPARATOR = '-';

    /** Letters, digits and a little punctuation; no separator, no whitespace. */
    public const PATTERN = '/^[\p{L}\p{N}._\/]{1,12}$/u';

    public const MAX_LENGTH = 12;

    /** @var array<string, string>|null */
    private static ?array $prefixes = null;

    /** The setting key that stores a document type's prefix. */
    public static function settingKey(string $type): string
    {
        return self::SETTING_PREFIX.$type;
    }

    /** @return list<string> */
    public static function settingKeys(): array
    {
        return array_map(fn (string $type) => self::settingKey($type), array_keys(self::DEFAULTS));
    }

    /** The configured prefix for a document type, without the separator. */
    public static function prefix(string $type): string
    {
        return self::prefixes()[$type] ?? self::DEFAULTS[$type] ?? strtoupper($type);
    }

    /**
     * A full document number: prefix, separator and the zero-padded sequence.
     *
     * `format('invoice', 42)` → `INV-000042`.
     */
    public static function format(string $type, int|string $sequence, int $pad = 6): string
    {
        return self::prefix($type).self::SEPARATOR.str_pad((string) $sequence, $pad, '0', STR_PAD_LEFT);
    }

    /** The prefix together with its separator, for callers that build the rest themselves. */
    public static function lead(string $type): string
    {
        return self::prefix($type).self::SEPARATOR;
    }

    public static function isValid(mixed $value): bool
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::prefixes();
    }

    /** @return array<string, string> */
    private static function prefixes(): array
    {
        if (self::$prefixes !== null) {
            return self::$prefixes;
        }

        $stored = [];
        try {
            $stored = Setting::query()
                ->whereIn('key', self::settingKeys())
                ->pluck('value', 'key')
                ->all();
        } catch (Throwable $e) {
            // Early bootstrap or a fresh install: the defaults stand in.
        }

        $prefixes = self::DEFAULTS;
        foreach (self::DEFAULTS as $type => $default) {
            $value = $stored[self::settingKey($type)] ?? null;
            if (self::isValid($value)) {
                $prefixes[$type] = trim((string) $value);
            }
        }

        return self::$prefixes = $prefixes;
    }

    /** Drops the per-request copy after the settings are saved. */
    public static function forget(): void
    {
        self::$prefixes = null;
    }
}
