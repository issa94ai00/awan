<?php

namespace App\Services\Accounting;

use App\Models\FixedAsset;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Brings an asset's depreciation up to a given month, one month at a time.
 *
 * The monthly run used to charge only the month it was asked about. A month
 * the schedule missed, or an asset registered after the fact with an earlier
 * purchase date, was then never charged: the run marked the asset as
 * depreciated through the later month and the gap stayed behind it for good,
 * pushing the whole schedule a month later for every month skipped.
 *
 * Each missing month is posted on its own, dated to its own last day and keyed
 * by it, so running this twice, or after the scheduler already did, changes
 * nothing.
 */
class FixedAssetDepreciation
{
    public function __construct(private LedgerPostingService $ledger)
    {
    }

    /** The last month that has fully ended, which is the one being closed. */
    public static function lastCompletedMonth(): Carbon
    {
        return now()->subMonthNoOverflow()->startOfMonth();
    }

    /**
     * The months still owed on an asset up to and including `$through`, with
     * the charge each one would carry.
     *
     * @return array<int, array{month: string, charge: float, accumulated_after: float}>
     */
    public function owed(FixedAsset $asset, Carbon $through): array
    {
        if ($asset->status !== FixedAsset::STATUS_ACTIVE || ! $asset->acquired_on) {
            return [];
        }

        $month = $asset->depreciated_through
            ? $asset->depreciated_through->copy()->addDay()->startOfMonth()
            : $asset->acquired_on->copy()->startOfMonth();

        $last = $through->copy()->startOfMonth();
        $monthly = $asset->monthlyCharge();
        $depreciable = $asset->depreciableAmount();
        $accumulated = round((float) $asset->accumulated_depreciation, 2);
        $months = [];

        while ($month->lte($last) && $monthly > 0) {
            // The last instalment takes what rounding left behind, and none
            // goes below the salvage value.
            $charge = round(min($monthly, $depreciable - $accumulated), 2);

            if ($charge <= 0) {
                break;
            }

            $accumulated = round($accumulated + $charge, 2);
            $months[] = [
                'month' => $month->format('Y-m'),
                'charge' => $charge,
                'accumulated_after' => $accumulated,
            ];

            $month->addMonthNoOverflow();
        }

        return $months;
    }

    /**
     * Posts every month owed on the asset through `$through`.
     *
     * Stops at the first month that cannot be posted — a closed period, most
     * often — rather than skipping it: charging April while March is still
     * missing would mark March as done.
     *
     * @return array{months: array<int, string>, amount: float, blocked: ?string}
     */
    public function charge(FixedAsset $asset, Carbon $through): array
    {
        $posted = [];
        $amount = 0.0;

        foreach ($this->owed($asset, $through) as $row) {
            $month = Carbon::createFromFormat('Y-m-d', $row['month'].'-01')->startOfMonth();

            try {
                DB::transaction(function () use ($asset, $month, $row) {
                    $entry = $this->ledger->postDepreciation($asset, $month, $row['charge']);

                    // An entry already under this month's key was counted when
                    // it was posted; only the marker moves.
                    $asset->update([
                        'accumulated_depreciation' => $entry?->wasRecentlyCreated
                            ? round((float) $asset->accumulated_depreciation + $row['charge'], 2)
                            : $asset->accumulated_depreciation,
                        'depreciated_through' => $month->copy()->endOfMonth()->toDateString(),
                    ]);
                });
            } catch (RuntimeException $e) {
                return ['months' => $posted, 'amount' => round($amount, 2), 'blocked' => $e->getMessage()];
            }

            $posted[] = $row['month'];
            $amount += $row['charge'];
        }

        return ['months' => $posted, 'amount' => round($amount, 2), 'blocked' => null];
    }

    /**
     * What a run through `$through` would post, asset by asset.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function preview(Carbon $through): Collection
    {
        return $this->candidates($through)
            ->map(function (FixedAsset $asset) use ($through) {
                $owed = $this->owed($asset, $through);

                if ($owed === []) {
                    return null;
                }

                $amount = round(array_sum(array_column($owed, 'charge')), 2);

                return [
                    'id' => $asset->id,
                    'asset_number' => $asset->asset_number,
                    'name' => $asset->name,
                    'category' => $asset->category,
                    'months' => array_column($owed, 'month'),
                    'amount' => $amount,
                    'net_book_value_after' => round((float) $asset->cost - (float) end($owed)['accumulated_after'], 2),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Posts every asset's owed months through `$through`.
     *
     * @return array{posted: array<int, array<string, mixed>>, blocked: array<int, array<string, mixed>>, total: float, entries: int}
     */
    public function run(Carbon $through): array
    {
        $posted = [];
        $blocked = [];
        $total = 0.0;
        $entries = 0;

        foreach ($this->candidates($through) as $asset) {
            $result = $this->charge($asset, $through);

            if ($result['months'] !== []) {
                $posted[] = [
                    'asset_number' => $asset->asset_number,
                    'name' => $asset->name,
                    'months' => $result['months'],
                    'amount' => $result['amount'],
                    'net_book_value' => $asset->fresh()->netBookValue(),
                ];
                $total += $result['amount'];
                $entries += count($result['months']);
            }

            if ($result['blocked']) {
                $blocked[] = [
                    'asset_number' => $asset->asset_number,
                    'name' => $asset->name,
                    'reason' => $result['blocked'],
                ];
            }
        }

        return ['posted' => $posted, 'blocked' => $blocked, 'total' => round($total, 2), 'entries' => $entries];
    }

    /** Active assets that arrived by the end of the month and are not yet charged through it. */
    private function candidates(Carbon $through): Collection
    {
        $end = $through->copy()->endOfMonth();

        return FixedAsset::where('status', FixedAsset::STATUS_ACTIVE)
            ->whereDate('acquired_on', '<=', $end)
            ->where(fn ($q) => $q->whereNull('depreciated_through')->orWhereDate('depreciated_through', '<', $end->toDateString()))
            ->orderBy('asset_number')
            ->get();
    }
}
