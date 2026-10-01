<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Models\JournalEntryHeader;
use App\Models\Warehouse;
use App\Services\Accounting\FixedAssetDepreciation;
use App\Services\Accounting\LedgerPostingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * The register of things bought to keep.
 *
 * Without one, a van or a shelving system had two possible homes and both were
 * wrong: an expense, which makes the month of purchase look disastrous and
 * every month after it flattering; or inventory, where it waits among the goods
 * for a sale that never comes.
 */
class FixedAssetController extends Controller
{
    public function __construct(
        private LedgerPostingService $ledger,
        private FixedAssetDepreciation $depreciation,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = FixedAsset::with(['warehouse:id,name', 'supplier:id,name']);

        $status = $request->input('status', FixedAsset::STATUS_ACTIVE);
        if (in_array($status, [FixedAsset::STATUS_ACTIVE, FixedAsset::STATUS_DISPOSED], true)) {
            $query->where('status', $status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $query->whereSearch(['name', 'asset_number'], $request->search);
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $assets = $query->orderBy('asset_number')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Fixed assets retrieved successfully',
            'data' => [
                'assets' => collect($assets->items())->map(fn (FixedAsset $asset) => $this->present($asset))->values(),
                'summary' => $this->summary(),
                'categories' => FixedAsset::whereNotNull('category')->where('category', '!=', '')
                    ->distinct()->orderBy('category')->pluck('category'),
                'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
                'pagination' => [
                    'current_page' => $assets->currentPage(),
                    'last_page' => $assets->lastPage(),
                    'per_page' => $assets->perPage(),
                    'total' => $assets->total(),
                    'has_more_pages' => $assets->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * The register as the balance sheet sees it, plus where depreciation
     * stands — worked out from the whole register, so the screen never adds
     * up a page and calls it the total.
     */
    private function summary(): array
    {
        $active = FixedAsset::where('status', FixedAsset::STATUS_ACTIVE)->get();
        $through = FixedAssetDepreciation::lastCompletedMonth();
        $owed = $this->depreciation->preview($through);

        $cost = round((float) $active->sum('cost'), 2);
        $accumulated = round((float) $active->sum('accumulated_depreciation'), 2);

        return [
            'active_count' => $active->count(),
            'disposed_count' => FixedAsset::where('status', FixedAsset::STATUS_DISPOSED)->count(),
            'fully_depreciated_count' => $active->filter->isFullyDepreciated()->count(),
            'cost' => $cost,
            'accumulated_depreciation' => $accumulated,
            'net_book_value' => round($cost - $accumulated, 2),
            // What a normal month costs from here on: assets already at their
            // salvage value no longer charge anything.
            'monthly_charge' => round($active->reject->isFullyDepreciated()->sum(fn (FixedAsset $a) => $a->monthlyCharge()), 2),
            'depreciation' => [
                'through' => $through->format('Y-m'),
                'behind_count' => $owed->count(),
                'behind_months' => $owed->sum(fn (array $row) => count($row['months'])),
                'behind_amount' => round((float) $owed->sum('amount'), 2),
            ],
        ];
    }

    /** One register row, with the figures the screen would otherwise have to derive. */
    private function present(FixedAsset $asset): array
    {
        $depreciable = $asset->depreciableAmount();

        return array_merge($asset->toArray(), [
            'net_book_value' => $asset->netBookValue(),
            'monthly_charge' => $asset->monthlyCharge(),
            'depreciable_amount' => $depreciable,
            'depreciated_percent' => $depreciable > 0
                ? round(min(100, (float) $asset->accumulated_depreciation / $depreciable * 100), 1)
                : 100.0,
            'is_fully_depreciated' => $asset->isFullyDepreciated(),
            'remaining_months' => $asset->status === FixedAsset::STATUS_ACTIVE ? $asset->remainingMonths() : 0,
            'last_charge_month' => $asset->status === FixedAsset::STATUS_ACTIVE ? $asset->lastChargeMonth() : null,
            // What it fetched against what it was still carried at.
            'disposal_result' => $asset->status === FixedAsset::STATUS_DISPOSED
                ? round((float) $asset->disposal_proceeds - $asset->netBookValue(), 2)
                : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            // An entry dated in the future would sit in the ledger before the
            // thing was bought.
            'acquired_on' => 'required|date|before_or_equal:today',
            'cost' => 'required|numeric|min:0.01',
            'salvage_value' => 'nullable|numeric|min:0|lt:cost',
            'useful_life_months' => 'required|integer|min:1|max:600',
            'settlement' => 'nullable|in:credit,cash,bank',
            // Bought on account, the payable has to belong to somebody:
            // without a supplier the control account grows and no supplier's
            // balance does, and payables stop agreeing with the ledger.
            'supplier_id' => [
                Rule::requiredIf(fn () => ($request->input('settlement') ?: 'credit') === 'credit'),
                'nullable',
                'exists:suppliers,id',
            ],
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'notes' => 'nullable|string|max:1000',
        ], [
            'supplier_id.required' => 'اختر المورد الذي يُستحق له ثمن الأصل المشترى على الحساب.',
            'acquired_on.before_or_equal' => 'تاريخ الاقتناء لا يمكن أن يكون في المستقبل.',
            'salvage_value.lt' => 'القيمة التخريدية يجب أن تقل عن التكلفة، وإلا فلا شيء يُهلك.',
            'useful_life_months.required' => 'يجب تحديد العمر الإنتاجي بالأشهر ليُوزَّع عليه القسط',
        ]);

        try {
            $asset = DB::transaction(function () use ($validated) {
                $asset = FixedAsset::create($validated + [
                    'asset_number' => 'FA-'.str_pad(
                        (string) (((int) FixedAsset::withTrashed()->max('id')) + 1), 5, '0', STR_PAD_LEFT
                    ),
                    'salvage_value' => $validated['salvage_value'] ?? 0,
                    'accumulated_depreciation' => 0,
                    'status' => FixedAsset::STATUS_ACTIVE,
                    'created_by' => auth()->id(),
                ]);

                $this->ledger->postAssetAcquisition($asset, $validated['settlement'] ?? 'credit');

                // Bought on account: the supplier is owed for it like any other
                // purchase, so the payables subsidiary keeps matching.
                if (($validated['settlement'] ?? 'credit') === 'credit' && ! empty($validated['supplier_id'])) {
                    \App\Models\Supplier::find($validated['supplier_id'])?->updateBalance((float) $asset->cost);
                }

                return $asset;
            });
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذّر ترحيل قيد اقتناء الأصل: '.$e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الأصل وترحيل قيد اقتنائه',
            'data' => $asset,
        ], 201);
    }

    public function show(FixedAsset $fixedAsset): JsonResponse
    {
        $fixedAsset->load(['warehouse:id,name', 'supplier:id,name', 'creator:id,name']);

        $keys = [$fixedAsset->acquisitionKey(), $fixedAsset->disposalKey()];
        $entries = JournalEntryHeader::where(fn ($q) => $q->whereIn('posting_key', $keys)
            ->orWhere('posting_key', 'like', 'depreciation:'.$fixedAsset->id.':%'))
            ->orderBy('entry_date')->orderBy('id')
            ->get(['id', 'entry_number', 'entry_date', 'posting_key', 'description', 'total_debit', 'status']);

        $posted = $entries->filter(fn ($e) => str_starts_with((string) $e->posting_key, 'depreciation:'));
        $lastCompleted = FixedAssetDepreciation::lastCompletedMonth();

        // What has been charged, then what is still to come at the same pace.
        // Months already ended but not yet charged are marked as due, so a
        // register the run is behind on says so.
        $schedule = $posted->map(fn ($e) => [
            'month' => substr((string) $e->posting_key, strrpos((string) $e->posting_key, ':') + 1),
            'charge' => round((float) $e->total_debit, 2),
            'state' => 'posted',
            'entry_id' => $e->id,
            'entry_number' => $e->entry_number,
        ])->values()->all();

        $accumulated = 0.0;
        foreach ($schedule as $i => $row) {
            $accumulated = round($accumulated + $row['charge'], 2);
            $schedule[$i]['net_book_value_after'] = round((float) $fixedAsset->cost - $accumulated, 2);
        }

        $horizon = $fixedAsset->acquired_on->copy()->addMonthsNoOverflow($fixedAsset->useful_life_months + 600);
        foreach ($this->depreciation->owed($fixedAsset, $horizon) as $row) {
            $schedule[] = [
                'month' => $row['month'],
                'charge' => $row['charge'],
                'state' => $row['month'] <= $lastCompleted->format('Y-m') ? 'due' : 'planned',
                'entry_id' => null,
                'entry_number' => null,
                'net_book_value_after' => round((float) $fixedAsset->cost - $row['accumulated_after'], 2),
            ];
        }

        $entryFor = fn (string $key) => optional($entries->firstWhere('posting_key', $key), fn ($e) => [
            'id' => $e->id,
            'entry_number' => $e->entry_number,
            'entry_date' => $e->entry_date?->toDateString(),
            'amount' => round((float) $e->total_debit, 2),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fixed asset retrieved successfully',
            'data' => array_merge($this->present($fixedAsset), [
                'schedule' => $schedule,
                'acquisition_entry' => $entryFor($fixedAsset->acquisitionKey()),
                'disposal_entry' => $entryFor($fixedAsset->disposalKey()),
                'last_completed_month' => $lastCompleted->format('Y-m'),
            ]),
        ]);
    }

    /**
     * Only what describes the asset changes after it is registered. Cost,
     * salvage value, life and date are what the posted entries were made
     * from; changing them here would leave the ledger telling another story.
     */
    public function update(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $fixedAsset->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات الأصل',
            'data' => $this->present($fixedAsset->fresh(['warehouse:id,name', 'supplier:id,name'])),
        ]);
    }

    /** What a depreciation run through a month would post, before it does. */
    public function depreciationPreview(Request $request): JsonResponse
    {
        $month = $this->runMonth($request);

        if ($month instanceof JsonResponse) {
            return $month;
        }

        $rows = $this->depreciation->preview($month);

        return response()->json([
            'success' => true,
            'message' => 'Depreciation preview',
            'data' => [
                'month' => $month->format('Y-m'),
                'last_completed_month' => FixedAssetDepreciation::lastCompletedMonth()->format('Y-m'),
                'assets' => $rows,
                'total' => round((float) $rows->sum('amount'), 2),
                'entries' => $rows->sum(fn (array $row) => count($row['months'])),
            ],
        ]);
    }

    /**
     * Posts depreciation for every active asset through a month, catching up
     * any month missed. The same run the scheduler makes on the first of the
     * month, and just as safe to repeat.
     */
    public function depreciate(Request $request): JsonResponse
    {
        $month = $this->runMonth($request);

        if ($month instanceof JsonResponse) {
            return $month;
        }

        $result = $this->depreciation->run($month);

        return response()->json([
            'success' => $result['blocked'] === [] || $result['entries'] > 0,
            'message' => $result['entries'] > 0
                ? 'تم ترحيل '.$result['entries'].' قيد إهلاك'
                : ($result['blocked'] === [] ? 'لا شيء لإهلاكه — الأصول محدَّثة' : 'تعذّر ترحيل الإهلاك'),
            'data' => $result + ['month' => $month->format('Y-m')],
        ], $result['blocked'] !== [] && $result['entries'] === 0 ? 422 : 200);
    }

    /** The month a run is asked for; only a month that has ended can be charged. */
    private function runMonth(Request $request): Carbon|JsonResponse
    {
        $request->validate(['month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);

        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m-d', $request->month.'-01')->startOfMonth()
            : FixedAssetDepreciation::lastCompletedMonth();

        if ($month->copy()->endOfMonth()->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => 'الشهر '.$month->format('Y-m').' لم ينتهِ بعد — الإهلاك يُحتسب عن شهر مكتمل.',
                'data' => null,
            ], 422);
        }

        return $month;
    }

    /**
     * Retires an asset — sold, scrapped, or simply gone.
     *
     * Both the cost and its accumulated depreciation leave together; taking one
     * without the other would leave the books owning depreciation on something
     * the business no longer has.
     */
    public function dispose(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        if ($fixedAsset->status !== FixedAsset::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الأصل مستبعد مسبقاً.',
                'data' => null,
            ], 422);
        }

        $validated = $request->validate([
            'disposed_on' => [
                'nullable', 'date', 'before_or_equal:today',
                'after_or_equal:'.$fixedAsset->acquired_on->toDateString(),
                // A month already charged cannot be before the asset left:
                // that depreciation would belong to something no longer owned.
                ...($fixedAsset->depreciated_through
                    ? ['after_or_equal:'.$fixedAsset->depreciated_through->copy()->startOfMonth()->toDateString()]
                    : []),
            ],
            'proceeds' => 'nullable|numeric|min:0',
            'settlement' => 'nullable|in:cash,bank',
            'charge_to_date' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ], [
            'disposed_on.before_or_equal' => 'تاريخ الاستبعاد لا يمكن أن يكون في المستقبل.',
            'disposed_on.after_or_equal' => 'تاريخ الاستبعاد يسبق تاريخ الاقتناء أو شهراً حُمِّل إهلاكه بالفعل.',
        ]);

        $disposedOn = Carbon::parse($validated['disposed_on'] ?? now()->toDateString());

        try {
            DB::transaction(function () use ($request, $fixedAsset, $validated, $disposedOn) {
                // The months it was used before it went are charged first, so
                // the gain or loss compares the price with what it was really
                // still worth rather than with a figure months out of date.
                // No charge for the month it leaves in.
                if ($request->boolean('charge_to_date', true)) {
                    $through = $disposedOn->copy()->startOfMonth()->subMonthNoOverflow();
                    $through = $through->min(FixedAssetDepreciation::lastCompletedMonth());
                    $caught = $this->depreciation->charge($fixedAsset, $through);

                    if ($caught['blocked']) {
                        throw new RuntimeException($caught['blocked']);
                    }
                }

                $fixedAsset->update([
                    'status' => FixedAsset::STATUS_DISPOSED,
                    'disposed_on' => $disposedOn->toDateString(),
                    'disposal_proceeds' => round((float) ($validated['proceeds'] ?? 0), 5),
                    'notes' => $validated['notes'] ?? $fixedAsset->notes,
                ]);

                $this->ledger->postAssetDisposal(
                    $fixedAsset->refresh(),
                    (float) ($validated['proceeds'] ?? 0),
                    $validated['settlement'] ?? 'cash'
                );
            });
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذّر ترحيل قيد الاستبعاد: '.$e->getMessage(),
                'data' => null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم استبعاد الأصل وترحيل قيده',
            'data' => $this->present($fixedAsset->refresh()),
        ]);
    }

    /**
     * An asset that has reached the books is not deleted. Its cost is on the
     * balance sheet and its depreciation in closed periods; retiring it is a
     * disposal, which leaves a trail.
     */
    public function destroy(FixedAsset $fixedAsset): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'لا يُحذف أصل رُحّل قيده. استخدم الاستبعاد ليبقى أثره في الدفاتر.',
            'data' => null,
        ], 422);
    }
}
