<?php

namespace App\Console\Commands;

use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Puts what the business already owns onto its opening balance sheet.
 *
 * A clean ledger and a full warehouse do not go together. Stock sitting on the
 * shelf with no value in the books makes the first sale post a cost of goods
 * against an inventory account that has nothing in it, so the asset runs
 * negative and the margin is nonsense — the books say the goods appeared from
 * nowhere and left at a cost.
 *
 * The opening entry is how a business that already exists starts keeping
 * accounts: every asset it holds is recognised, and the other side is capital,
 * because that is what the owner has put into it.
 *
 *   Dr  Inventory — each warehouse   what its stock actually cost
 *   Dr  Cash                         what is in the till
 *   Dr  Bank                         what is in the account
 *       Cr  Capital                       the total of them
 *
 * Each component posts under its own key, so the inventory can be booked today
 * and the cash tomorrow when somebody has counted it, and neither can be
 * posted twice.
 *
 * Inventory is valued from the FIFO cost layers rather than from the product's
 * current cost price: the layers record what was actually paid for the units
 * still on the shelf, which is the figure the ledger has to agree with when
 * those units are eventually sold.
 */
class AccountingOpeningBalance extends Command
{
    protected $signature = 'accounting:opening-balance
                            {--inventory : Book the value of stock on hand}
                            {--cash=0 : Opening balance of the cash box}
                            {--bank=0 : Opening balance of the bank account}
                            {--date= : Entry date (defaults to today)}
                            {--dry-run : Report what would be posted and stop}';

    protected $description = 'Post opening balances for stock on hand, cash and bank against capital';

    public function handle(LedgerPostingService $ledger): int
    {
        $date = $this->option('date') ?: now()->toDateString();
        $cash = round((float) $this->option('cash'), 2);
        $bank = round((float) $this->option('bank'), 2);
        $dryRun = (bool) $this->option('dry-run');

        if (! $this->option('inventory') && $cash <= 0 && $bank <= 0) {
            $this->warn('لم تُطلب أي أرصدة. استخدم --inventory و/أو --cash= و--bank=.');

            return self::SUCCESS;
        }

        $rows = [];

        if ($this->option('inventory')) {
            foreach ($this->stockValueByWarehouse() as $warehouseId => $value) {
                $rows[] = [
                    'key' => 'opening_balance:inventory:'.$warehouseId,
                    'label' => 'مخزون - '.(Warehouse::find($warehouseId)?->name ?? ('#'.$warehouseId)),
                    'amount' => $value,
                    'account' => ['account_id' => $ledger->inventoryAccountIdFor((int) $warehouseId)],
                ];
            }
        }

        if ($cash > 0) {
            $rows[] = ['key' => 'opening_balance:cash', 'label' => 'الصندوق', 'amount' => $cash, 'account' => ['role' => 'cash']];
        }

        if ($bank > 0) {
            $rows[] = ['key' => 'opening_balance:bank', 'label' => 'البنك', 'amount' => $bank, 'account' => ['role' => 'bank']];
        }

        if ($rows === []) {
            $this->warn('لا توجد أرصدة لترحيلها — المخزون بلا قيمة والمبالغ صفر.');

            return self::SUCCESS;
        }

        $this->table(
            ['المفتاح', 'البند', 'المبلغ'],
            array_map(fn ($row) => [$row['key'], $row['label'], number_format($row['amount'], 2)], $rows)
        );
        $this->line('الإجمالي مقابل رأس المال: '.number_format(array_sum(array_column($rows, 'amount')), 2));

        if ($dryRun) {
            $this->info('معاينة فقط — لم يُكتب أي قيد.');

            return self::SUCCESS;
        }

        $posted = 0;

        foreach ($rows as $row) {
            $entry = $ledger->post(
                key: $row['key'],
                date: $date,
                description: 'رصيد افتتاحي - '.$row['label'],
                lines: [
                    $row['account'] + ['debit' => $row['amount'], 'description' => 'رصيد افتتاحي - '.$row['label']],
                    ['role' => 'capital', 'credit' => $row['amount'], 'description' => 'رأس المال - رصيد افتتاحي '.$row['label']],
                ],
                module: 'opening',
            );

            if ($entry?->wasRecentlyCreated) {
                $posted++;
                $this->line('  ✓ '.$entry->entry_number.' — '.$row['label']);
            } else {
                // Already booked: the whole point of a key per component.
                $this->line('  • '.$row['label'].' مُرحَّل مسبقاً، تُرك كما هو.');
            }
        }

        $this->newLine();
        $this->info("تم ترحيل {$posted} قيداً افتتاحياً.");

        return self::SUCCESS;
    }

    /**
     * What the stock still on the shelf actually cost, per warehouse.
     *
     * Read from the remaining FIFO layers, which record the price paid for the
     * units that have not been sold yet. Valuing from `products.cost_price`
     * instead would price today's shelf at today's cost and disagree with
     * every cost of sale posted later.
     *
     * @return array<int,float>
     */
    private function stockValueByWarehouse(): array
    {
        return DB::table('inventory_cost_layers')
            ->where('remaining_quantity', '>', 0)
            ->selectRaw('warehouse_id, ROUND(SUM(remaining_quantity * unit_cost), 2) as value')
            ->groupBy('warehouse_id')
            ->havingRaw('ROUND(SUM(remaining_quantity * unit_cost), 2) > 0')
            ->pluck('value', 'warehouse_id')
            ->map(fn ($value) => round((float) $value, 2))
            ->all();
    }
}
