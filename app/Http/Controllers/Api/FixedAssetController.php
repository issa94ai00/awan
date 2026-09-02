<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    public function __construct(private LedgerPostingService $ledger)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = FixedAsset::with(['warehouse', 'supplier']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $assets = $query->orderBy('asset_number')->paginate($request->input('per_page', 20));

        $present = fn (FixedAsset $asset) => array_merge($asset->toArray(), [
            'net_book_value' => $asset->netBookValue(),
            'monthly_charge' => $asset->monthlyCharge(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fixed assets retrieved successfully',
            'data' => [
                'assets' => collect($assets->items())->map($present)->values(),
                // The two figures the balance sheet shows for the register, so
                // the screen does not add up a page and call it the total.
                'totals' => [
                    'cost' => round((float) FixedAsset::where('status', FixedAsset::STATUS_ACTIVE)->sum('cost'), 2),
                    'accumulated_depreciation' => round(
                        (float) FixedAsset::where('status', FixedAsset::STATUS_ACTIVE)->sum('accumulated_depreciation'), 2
                    ),
                ],
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'acquired_on' => 'required|date',
            'cost' => 'required|numeric|min:0.01',
            'salvage_value' => 'nullable|numeric|min:0|lt:cost',
            'useful_life_months' => 'required|integer|min:1|max:600',
            'settlement' => 'nullable|in:credit,cash,bank',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'notes' => 'nullable|string|max:1000',
        ], [
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
        $fixedAsset->load(['warehouse', 'supplier', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Fixed asset retrieved successfully',
            'data' => array_merge($fixedAsset->toArray(), [
                'net_book_value' => $fixedAsset->netBookValue(),
                'monthly_charge' => $fixedAsset->monthlyCharge(),
                'depreciable_amount' => $fixedAsset->depreciableAmount(),
            ]),
        ]);
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
            'disposed_on' => 'nullable|date',
            'proceeds' => 'nullable|numeric|min:0',
            'settlement' => 'nullable|in:cash,bank',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::transaction(function () use ($fixedAsset, $validated) {
                $fixedAsset->update([
                    'status' => FixedAsset::STATUS_DISPOSED,
                    'disposed_on' => $validated['disposed_on'] ?? now()->toDateString(),
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
            'data' => $fixedAsset->refresh(),
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
