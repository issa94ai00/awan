<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Currency management, and the list the storefront and the app read.
 *
 * Amounts everywhere in this system are stored and posted in the base currency.
 * What is exposed here lets a client *display* them in another one; it never
 * changes what is charged. The clients are expected to keep saying which
 * currency the money actually moves in — see the note on CurrencyService.
 */
class CurrencyController extends Controller
{
    public function __construct(
        private CurrencyService $currencies
    ) {}

    /** Public: the currencies a shopper may view prices in, with today's rates. */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'base' => $this->currencies->baseCode(),
                'currencies' => $this->currencies->active()->values(),
            ],
        ]);
    }

    /** Admin: every currency, active or not, with its rate history. */
    public function adminIndex(): JsonResponse
    {
        $rows = Currency::query()
            ->with(['rates' => fn ($q) => $q->with('creator:id,name')->limit(10)])
            ->orderByDesc('is_base')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(fn (Currency $currency) => $this->currencies->present($currency) + [
                'id' => $currency->id,
                'is_active' => $currency->is_active,
                'sort_order' => $currency->sort_order,
                'rate_history' => $currency->rates->map(fn ($rate) => [
                    'id' => $rate->id,
                    'rate' => $rate->rate,
                    'effective_at' => $rate->effective_at?->toIso8601String(),
                    'note' => $rate->note,
                    'created_by' => $rate->creator?->name,
                ])->values(),
            ]);

        return response()->json([
            'success' => true,
            'data' => ['base' => $this->currencies->baseCode(), 'currencies' => $rows],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:8', 'alpha', Rule::unique('currencies', 'code')],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:12'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:4'],
            'rounding_step' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            // Supplied on creation so a new currency is usable immediately
            // rather than sitting rateless and unconvertible.
            'rate' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $currency = Currency::create($data + ['is_active' => true]);

        if (! empty($data['rate'])) {
            $this->currencies->recordRate($currency, $data['rate']);
        }

        $this->currencies->flushCache();

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة العملة.',
            'data' => $this->currencies->present($currency->refresh()),
        ], 201);
    }

    public function update(Request $request, Currency $currency): JsonResponse
    {
        $data = $request->validate([
            'name_ar' => ['sometimes', 'required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'symbol' => ['sometimes', 'required', 'string', 'max:12'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:4'],
            'rounding_step' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // The code is what every stored amount's currency column refers to, so
        // it is set once. Renaming it would orphan the rows that name it.
        unset($data['code']);

        if (array_key_exists('is_active', $data) && ! $data['is_active'] && $currency->is_base) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تعطيل عملة الأساس.',
            ], 422);
        }

        $currency->update($data);
        $this->currencies->flushCache();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث العملة.',
            'data' => $this->currencies->present($currency->refresh()),
        ]);
    }

    /** Records a new rate. Appends — the previous rate stays readable. */
    public function storeRate(Request $request, Currency $currency): JsonResponse
    {
        $data = $request->validate([
            'rate' => ['required', 'numeric', 'gt:0'],
            'effective_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $rate = $this->currencies->recordRate(
                $currency,
                $data['rate'],
                isset($data['effective_at']) ? new \DateTimeImmutable($data['effective_at']) : null,
                $data['note'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ سعر الصرف.',
            'data' => ['rate' => $rate->rate, 'effective_at' => $rate->effective_at?->toIso8601String()],
        ], 201);
    }

    /**
     * Makes a currency the base.
     *
     * Refused while other currencies hold rates that were quoted against the
     * current base: those numbers mean "per one old base unit", and keeping
     * them after the base moves would misprice every one of them. The admin
     * re-enters the rates against the new base, which is the only honest way
     * through.
     */
    public function setBase(Currency $currency): JsonResponse
    {
        if ($currency->is_base) {
            return response()->json(['success' => true, 'message' => 'هذه عملة الأساس بالفعل.']);
        }

        $this->currencies->setBase($currency);

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير عملة الأساس. الأرصدة المخزّنة لم تُحوَّل — أعد إدخال أسعار الصرف مقابل العملة الجديدة.',
            'data' => ['base' => $this->currencies->baseCode()],
        ]);
    }
}
