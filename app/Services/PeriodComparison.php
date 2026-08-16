<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * What a figure did compared with the period before it.
 *
 * The analytics cards used to carry percentages written into the template —
 * `+12.5%`, `+5.2%`, `+2.1%` — that were the same on every install, every day,
 * whatever the data said. A number nobody computed is worse than no number:
 * it is read as a fact and acted on.
 *
 * The comparison window is the equal-length stretch immediately before the one
 * being viewed, so "last 30 days" is judged against the 30 days before it. That
 * is the only reading that stays honest when the user changes the range.
 *
 * A change is `null` — never zero, never invented — whenever there is nothing
 * to compare against. Callers render a dash for that, because "no prior data"
 * and "flat" are different statements.
 */
class PeriodComparison
{
    /**
     * The equal-length window immediately before `$from..$to`, inclusive.
     *
     * @return array{from: string, to: string}
     */
    public static function previousWindow(string|DateTimeInterface $from, string|DateTimeInterface $to): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();

        // A reversed range is a caller mistake, not a reason to return garbage.
        if ($end->lessThan($start)) {
            [$start, $end] = [$end, $start];
        }

        // Inclusive: 1 Jan–31 Jan is 31 days, so the prior window ends 31 Dec.
        $lengthInDays = $start->diffInDays($end) + 1;

        $previousEnd = $start->subDay();
        $previousStart = $previousEnd->subDays($lengthInDays - 1);

        return [
            'from' => $previousStart->toDateString(),
            'to' => $previousEnd->toDateString(),
        ];
    }

    /**
     * A metric stated against its own past.
     *
     * `change_percent` is null when the previous value is zero: going from
     * nothing to something is not a percentage, and reporting it as `+100%`
     * (or dividing by zero) says something the data does not support. The
     * `direction` still tells the reader which way it moved.
     *
     * @return array{current: float, previous: float, change: float, change_percent: float|null, direction: string}
     */
    public static function compare(float|int|string|null $current, float|int|string|null $previous): array
    {
        $currentValue = self::toFloat($current);
        $previousValue = self::toFloat($previous);
        $change = $currentValue - $previousValue;

        $changePercent = null;
        if (abs($previousValue) > 0.0000001) {
            $changePercent = round(($change / abs($previousValue)) * 100, 1);
        }

        return [
            'current' => round($currentValue, 2),
            'previous' => round($previousValue, 2),
            'change' => round($change, 2),
            'change_percent' => $changePercent,
            'direction' => self::direction($change),
        ];
    }

    /**
     * Whether the movement is worth colouring, and which way.
     *
     * Rounded before comparing so a floating-point crumb does not present a
     * flat metric as a rise.
     */
    private static function direction(float $change): string
    {
        $rounded = round($change, 2);

        return match (true) {
            $rounded > 0 => 'up',
            $rounded < 0 => 'down',
            default => 'flat',
        };
    }

    private static function toFloat(float|int|string|null $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
