<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * The server's half of Settings → Numbers & decimals.
 *
 * `resources/js/utils/numberFormat.js` writes every figure on screen; this
 * writes the figures the server puts in front of a person — a validation
 * message that quotes a balance, a currency formatted for the storefront — so
 * a payment error reads in the same style as the invoice beside it.
 *
 * Display only, like its JavaScript twin: amounts stored and posted keep their
 * own precision.
 */
class NumberFormat
{
    /** Mirrors NUMBER_DEFAULTS in numberFormat.js — the two must agree. */
    public const DEFAULTS = [
        'number_digits' => 'auto',
        'number_decimal_separator' => 'auto',
        'number_thousands_separator' => 'auto',
        'number_money_decimals' => 'currency',
        'number_quantity_decimals' => '3',
        'number_percent_decimals' => '1',
        'number_rounding_mode' => 'half_up',
        'number_trailing_zeros' => 'keep',
        'number_negative_format' => 'minus',
        'number_currency_display' => 'code',
        'number_currency_position' => 'after',
    ];

    /** The values each setting accepts; the settings form validates against these. */
    public const OPTIONS = [
        'number_digits' => ['auto', 'latin', 'arabic'],
        'number_decimal_separator' => ['auto', '.', ',', '٫'],
        'number_thousands_separator' => ['auto', ',', '.', '٬', 'space', 'apostrophe', 'none'],
        'number_money_decimals' => ['currency', '0', '1', '2', '3', '4'],
        'number_quantity_decimals' => ['0', '1', '2', '3', '4'],
        'number_percent_decimals' => ['0', '1', '2', '3'],
        'number_rounding_mode' => ['half_up', 'half_even', 'half_down', 'ceil', 'floor', 'truncate'],
        'number_trailing_zeros' => ['keep', 'drop_whole', 'drop_all'],
        'number_negative_format' => ['minus', 'parentheses', 'trailing'],
        'number_currency_display' => ['code', 'symbol'],
        'number_currency_position' => ['after', 'before'],
    ];

    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /** @var array<string, string>|null */
    private static ?array $settings = null;

    /** @return array<string, string> */
    public static function settings(): array
    {
        if (self::$settings !== null) {
            return self::$settings;
        }

        $stored = [];
        try {
            $stored = Setting::query()
                ->whereIn('key', array_keys(self::DEFAULTS))
                ->pluck('value', 'key')
                ->all();
        } catch (Throwable $e) {
            // Early bootstrap or a fresh install: the defaults stand in.
        }

        $settings = self::DEFAULTS;
        foreach ($stored as $key => $value) {
            if (in_array((string) $value, self::OPTIONS[$key] ?? [], true)) {
                $settings[$key] = (string) $value;
            }
        }

        return self::$settings = $settings;
    }

    /** Drops the per-request copy after the settings are saved. */
    public static function forget(): void
    {
        self::$settings = null;
    }

    public static function round(float|int|string $value, int $places = 0, ?string $mode = null): float
    {
        $mode ??= self::settings()['number_rounding_mode'];
        $value = (float) $value;
        $places = max(0, min(10, $places));

        if ($value == 0.0) {
            return 0.0;
        }

        // Fifteen significant digits first drops binary noise (1.005 is 1.00499…).
        $scaled = (float) sprintf('%.15g', $value * (10 ** $places));
        $sign = $scaled < 0 ? -1 : 1;
        $abs = abs($scaled);
        $whole = floor($abs);
        $fraction = $abs - $whole;

        $rounded = match ($mode) {
            'half_even' => $sign * ($fraction > 0.5 || ($fraction == 0.5 && fmod($whole, 2) != 0) ? $whole + 1 : $whole),
            'half_down' => $sign * ($fraction > 0.5 ? $whole + 1 : $whole),
            'ceil' => ceil($scaled),
            'floor' => floor($scaled),
            'truncate' => $sign * $whole,
            default => $sign * round($abs),
        };

        $result = $rounded / (10 ** $places);

        // Folds -0.0 into 0.0, so -0.001 never prints as "-0.00".
        return $result == 0.0 ? 0.0 : $result;
    }

    /**
     * @param  array{trailing_zeros?: string, prefix?: string, suffix?: string}  $options
     */
    public static function decimal(float|int|string $value, int $maxPlaces = 2, int $minPlaces = 0, array $options = []): string
    {
        $settings = self::settings();
        $minPlaces = min($minPlaces, $maxPlaces);
        $rounded = self::round($value, $maxPlaces);

        $parts = explode('.', number_format(abs($rounded), $maxPlaces, '.', ''));
        $whole = $parts[0];
        $fraction = str_pad(rtrim($parts[1] ?? '', '0'), $minPlaces, '0');

        $trailing = $options['trailing_zeros'] ?? 'keep';
        if ($trailing === 'drop_all' || ($trailing === 'drop_whole' && trim($fraction, '0') === '')) {
            $fraction = rtrim($fraction, '0');
        }

        $arabic = self::arabicDigits();
        $decimal = self::decimalSeparator($arabic);
        $grouping = self::thousandsSeparator($arabic, $decimal);

        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', "\0", $whole);
        $body = $fraction !== '' ? $whole.'.'.$fraction : $whole;

        if ($arabic) {
            $body = str_replace(range('0', '9'), self::ARABIC_DIGITS, $body);
        }
        // strtr swaps both at once, so a '.' grouping mark is not then read as the decimal.
        $body = strtr($body, ["\0" => $grouping, '.' => $decimal]);
        $body = ($options['prefix'] ?? '').$body.($options['suffix'] ?? '');

        if ($rounded >= 0) {
            return $body;
        }

        return match ($settings['number_negative_format']) {
            'parentheses' => '('.$body.')',
            'trailing' => $body.'-',
            default => ($arabic ? "\u{061C}" : '').'-'.$body,
        };
    }

    /** An amount in a message: the configured money precision, no currency label. */
    public static function amount(float|int|string $value, int $currencyPlaces = 2): string
    {
        $places = self::moneyDecimals($currencyPlaces);

        return self::decimal($value, $places, $places, [
            'trailing_zeros' => self::settings()['number_trailing_zeros'],
        ]);
    }

    public static function moneyDecimals(int $currencyPlaces = 2): int
    {
        $chosen = self::settings()['number_money_decimals'];

        return $chosen === 'currency' ? max(0, $currencyPlaces) : (int) $chosen;
    }

    public static function quantity(float|int|string $value): string
    {
        return self::decimal($value, (int) self::settings()['number_quantity_decimals']);
    }

    private static function arabicDigits(): bool
    {
        $digits = self::settings()['number_digits'];

        return $digits === 'arabic' || ($digits === 'auto' && app()->getLocale() !== 'en');
    }

    private static function decimalSeparator(bool $arabic): string
    {
        $chosen = self::settings()['number_decimal_separator'];

        return $chosen === 'auto' ? ($arabic ? '٫' : '.') : $chosen;
    }

    private static function thousandsSeparator(bool $arabic, string $decimal): string
    {
        $separator = match ($chosen = self::settings()['number_thousands_separator']) {
            'auto' => $arabic ? '٬' : ',',
            'space' => "\u{202F}",
            'apostrophe' => "\u{2019}",
            'none' => '',
            default => $chosen,
        };

        return $separator === $decimal ? '' : $separator;
    }
}
