<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Creates the main warehouse.
 *
 * Every stock record hangs off a warehouse, so this has to exist before any
 * product is seeded. It used to be created as a side effect of
 * WarehouseBinSeeder — matched on the English name "Main Warehouse" — which
 * meant the warehouse only appeared if bins were being seeded, and any other
 * seeder that named it differently would silently create a second one.
 *
 * Idempotent: it matches on the primary flag first, then on the code, so
 * re-seeding renames the existing warehouse rather than adding a duplicate.
 */
class WarehouseSeeder extends Seeder
{
    public const MAIN_CODE = 'WH-001';
    public const MAIN_NAME = 'المستودع الرئيسي';

    public function run(): void
    {
        $warehouse = $this->resolveMain();

        $warehouse->fill([
            'name' => self::MAIN_NAME,
            'code' => self::MAIN_CODE,
            'location_type' => 'warehouse',
            'is_active' => true,
            'is_primary' => true,
        ])->save();

        // Only one warehouse can be the primary one.
        Warehouse::where('id', '!=', $warehouse->id)->update(['is_primary' => false]);

        $this->command?->info("المستودع الرئيسي: #{$warehouse->id} {$warehouse->name} ({$warehouse->code})");

        // Create personal warehouses for users
        $personalWarehouses = [
            ['name' => 'wh-anas', 'code' => 'wh-anas'],
            ['name' => 'wh-ahmad', 'code' => 'wh-ahmad'],
            ['name' => 'wh-ayoub', 'code' => 'wh-ayoub'],
            ['name' => 'wh-hasan', 'code' => 'wh-hasan'],
            ['name' => 'wh-ayad', 'code' => 'wh-ayad'],
        ];

        foreach ($personalWarehouses as $warehouseData) {
            Warehouse::updateOrCreate(
                ['code' => $warehouseData['code']],
                [
                    'name' => $warehouseData['name'],
                    'code' => $warehouseData['code'],
                    'location_type' => 'warehouse',
                    'is_active' => true,
                    'is_primary' => false,
                ]
            );
        }

        $this->command?->info("تم إنشاء المستودعات الشخصية");
    }

    /**
     * Finds the warehouse that should become the main one, preferring an
     * existing primary so historical stock keeps pointing at the same record.
     */
    private function resolveMain(): Warehouse
    {
        return Warehouse::query()->where('is_primary', true)->first()
            ?? Warehouse::query()->where('code', self::MAIN_CODE)->first()
            ?? Warehouse::query()->orderBy('id')->first()
            ?? new Warehouse();
    }

    /** The main warehouse id, for seeders that need to attach stock to it. */
    public static function mainWarehouseId(): int
    {
        $warehouse = Warehouse::query()->where('is_primary', true)->first()
            ?? Warehouse::query()->where('code', self::MAIN_CODE)->first()
            ?? Warehouse::query()->orderBy('id')->first();

        if (!$warehouse) {
            throw new \RuntimeException('لا يوجد مستودع — شغّل WarehouseSeeder أولاً.');
        }

        return $warehouse->id;
    }
}
