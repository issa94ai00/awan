<?php

namespace App\Console\Commands;

use App\Services\ProductExcelService;
use Illuminate\Console\Command;

class ImportMenu extends Command
{
    protected $signature = 'products:import-menu {file : Absolute path to the products_menu.xlsx file}';

    protected $description = 'Import a multi-sheet menu file into warehouses named after each sheet';

    public function handle(ProductExcelService $service): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("الملف غير موجود: {$file}");
            return self::FAILURE;
        }

        $result = $service->importMenuFile($file);

        $this->info("تمت معالجة {$result['sheets']} ورقة عمل.");
        $this->info("مستودعات جديدة: {$result['warehouses_created']}");
        $this->info("منتجات جديدة: {$result['products_created']}");
        $this->info("منتجات مطابقة: {$result['products_matched']}");
        $this->info("صفوف مخزون: {$result['inventory_rows']}");

        if ($result['errors']) {
            $this->newLine();
            $this->warn('أخطاء (' . count($result['errors']) . '):');
            $this->table(
                ['الورقة', 'الصف', 'الرسالة'],
                array_map(
                    fn (array $e) => [$e['sheet'], $e['row'], $e['message']],
                    $result['errors']
                )
            );
        }

        return self::SUCCESS;
    }
}
