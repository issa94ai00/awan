<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LastTenCategoriesProductsSeeder extends Seeder
{
    /**
     * 30 products for each of the last 10 categories (IDs 32–41).
     *
     * @var array<int, array{category_id:int, category_slug:string, products:array<int,array{name_ar:string, name_en:string, sku:string, price:float, cost_price:float, unit:string, brand:string, stock:int}>}>
     */
    private const DATA = [
        // ── 32: Socket Sets & Tool Kits ──────────────────────────────────
        [
            'category_id' => 32,
            'category_slug' => 'socket-sets-tool-kits',
            'products' => [
                ['name_ar' => 'طقم كشتبان متر 1/2 - 40 قطعة', 'name_en' => 'Metric Socket Set 1/2" - 40pc', 'sku' => 'SKT-040M', 'price' => 85.00, 'cost_price' => 52.00, 'unit' => 'طقم', 'brand' => 'Stanley', 'stock' => 25],
                ['name_ar' => 'طقم كشتبان متر 1/4 - 30 قطعة', 'name_en' => 'Metric Socket Set 1/4" - 30pc', 'sku' => 'SKT-030MQ', 'price' => 55.00, 'cost_price' => 32.00, 'unit' => 'طقم', 'brand' => 'Stanley', 'stock' => 30],
                ['name_ar' => 'طقم كشتبان انجليزي 1/2 - 36 قطعة', 'name_en' => 'Imperial Socket Set 1/2" - 36pc', 'sku' => 'SKT-036I', 'price' => 90.00, 'cost_price' => 55.00, 'unit' => 'طقم', 'brand' => 'Bosch', 'stock' => 20],
                ['name_ar' => 'طقم كشتبان متر 3/8 - 25 قطعة', 'name_en' => 'Metric Socket Set 3/8" - 25pc', 'sku' => 'SKT-025M38', 'price' => 45.00, 'cost_price' => 28.00, 'unit' => 'طقم', 'brand' => 'Makita', 'stock' => 35],
                ['name_ar' => 'طقم كشتبان توركس - 20 قطعة', 'name_en' => 'Torx Socket Set - 20pc', 'sku' => 'SKT-020TX', 'price' => 38.00, 'cost_price' => 22.00, 'unit' => 'طقم', 'brand' => 'Stanley', 'stock' => 40],
                ['name_ar' => 'طقم عزقة كشتبان 1/2 - 12 قطعة', 'name_en' => 'Deep Socket Set 1/2" - 12pc', 'sku' => 'SKT-012D', 'price' => 35.00, 'cost_price' => 20.00, 'unit' => 'طقم', 'brand' => 'Bosch', 'stock' => 28],
                ['name_ar' => 'مفتاح ربط كشتبان 1/2 ب SIZE', 'name_en' => '1/2" Ratchet Handle', 'sku' => 'SKT-RH12', 'price' => 22.00, 'cost_price' => 12.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 50],
                ['name_ar' => 'مفتاح ربط كشتبان 1/4 ب SIZE', 'name_en' => '1/4" Ratchet Handle', 'sku' => 'SKT-RH14', 'price' => 15.00, 'cost_price' => 8.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 45],
                ['name_ar' => 'إطار تمديد كشتبان 1/2 - 25 سم', 'name_en' => '1/2" Extension Bar 250mm', 'sku' => 'SKT-EX25', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 60],
                ['name_ar' => 'إطار تمديد كشتبان 1/2 - 50 سم', 'name_en' => '1/2" Extension Bar 500mm', 'sku' => 'SKT-EX50', 'price' => 12.00, 'cost_price' => 7.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 40],
                ['name_ar' => 'وصلة كشتبان زاوية 1/2', 'name_en' => '1/2" Universal Joint', 'sku' => 'SKT-UJ12', 'price' => 6.00, 'cost_price' => 3.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 55],
                ['name_ar' => 'مفتاح توركس ب SIZE 8 قطع', 'name_en' => 'Torx Bit Set 8pc', 'sku' => 'SKT-TX08', 'price' => 12.00, 'cost_price' => 6.50, 'unit' => 'طقم', 'brand' => 'Bosch', 'stock' => 35],
                ['name_ar' => 'طقم كشتبان متر متكامل - 100 قطعة', 'name_en' => 'Complete Metric Socket Set - 100pc', 'sku' => 'SKT-100M', 'price' => 180.00, 'cost_price' => 110.00, 'unit' => 'طقم', 'brand' => 'Stanley', 'stock' => 15],
                ['name_ar' => 'طقم كشتبان سيارات - 45 قطعة', 'name_en' => 'Auto Socket Set - 45pc', 'sku' => 'SKT-045A', 'price' => 95.00, 'cost_price' => 58.00, 'unit' => 'طقم', 'brand' => 'Makita', 'stock' => 22],
                ['name_ar' => 'مفتاح ربط كشتبان 3/8 ب SIZE', 'name_en' => '3/8" Ratchet Handle', 'sku' => 'SKT-RH38', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 48],
                ['name_ar' => 'طقم مsbbs كشتبان 1/4 - 18 قطعة', 'name_en' => '1/4" Socket Set 18pc', 'sku' => 'SKT-018Q', 'price' => 30.00, 'cost_price' => 17.00, 'unit' => 'طقم', 'brand' => 'Bosch', 'stock' => 32],
                ['name_ar' => 'cope صندوق عدة كشتبان بلاستيك', 'name_en' => 'Plastic Socket Tool Box', 'sku' => 'SKT-BOX1', 'price' => 25.00, 'cost_price' => 14.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 40],
                ['name_ar' => 'cope صندوق عدة كشتبان مetal', 'name_en' => 'Metal Socket Tool Box', 'sku' => 'SKT-BOX2', 'price' => 35.00, 'cost_price' => 20.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 25],
                ['name_ar' => 'طقم كشتبان ألوان - 24 قطعة', 'name_en' => 'Color Coded Socket Set - 24pc', 'sku' => 'SKT-024C', 'price' => 42.00, 'cost_price' => 25.00, 'unit' => 'طقم', 'brand' => 'Makita', 'stock' => 20],
                ['name_ar' => 'مفتاح كشتبان ب Balance', 'name_en' => 'Flex Head Ratchet 1/2"', 'sku' => 'SKT-FH12', 'price' => 28.00, 'cost_price' => 16.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 30],
                ['name_ar' => 'cope صندوق عدة متنقل - 120 قطعة', 'name_en' => 'Portable Tool Case - 120pc', 'sku' => 'SKT-120P', 'price' => 220.00, 'cost_price' => 135.00, 'unit' => 'طقم', 'brand' => 'Bosch', 'stock' => 10],
                ['name_ar' => 'cope صندوق عدة سيارات - 80 قطعة', 'name_en' => 'Auto Tool Case - 80pc', 'sku' => 'SKT-080A', 'price' => 150.00, 'cost_price' => 90.00, 'unit' => 'طقم', 'brand' => 'Stanley', 'stock' => 18],
                ['name_ar' => 'cope صندوق عدة م공 - 60 قطعة', 'name_en' => 'Workshop Tool Case - 60pc', 'sku' => 'SKT-060W', 'price' => 110.00, 'cost_price' => 65.00, 'unit' => 'طقم', 'brand' => 'Makita', 'stock' => 22],
                ['name_ar' => 'cope صندوق عدة كشتبان ستانلس - 50 قطعة', 'name_en' => 'Stainless Socket Set - 50pc', 'sku' => 'SKT-050SS', 'price' => 130.00, 'cost_price' => 80.00, 'unit' => 'طقم', 'brand' => 'Bosch', 'stock' => 12],
                ['name_ar' => 'cope صندوق عدة كشتبان كروم - 35 قطعة', 'name_en' => 'Chrome Socket Set - 35pc', 'sku' => 'SKT-035CR', 'price' => 70.00, 'cost_price' => 42.00, 'unit' => 'طقم', 'brand' => 'Stanley', 'stock' => 28],
                ['name_ar' => 'cope صندوق عدة كشتبان ألومنيوم - 45 قطعة', 'name_en' => 'Aluminium Socket Set - 45pc', 'sku' => 'SKT-045AL', 'price' => 88.00, 'cost_price' => 53.00, 'unit' => 'طقم', 'brand' => 'Makita', 'stock' => 16],
                ['name_ar' => 'cope صندوق عدة كشتبان تيتانيوم - 20 قطعة', 'name_en' => 'Titanium Socket Set - 20pc', 'sku' => 'SKT-020TI', 'price' => 65.00, 'cost_price' => 38.00, 'unit' => 'طقم', 'brand' => 'Bosch', 'stock' => 20],
                ['name_ar' => 'cope صندوق عدة كشتبان كربون - 30 قطعة', 'name_en' => 'Carbon Socket Set - 30pc', 'sku' => 'SKT-030CB', 'price' => 50.00, 'cost_price' => 29.00, 'unit' => 'طقم', 'brand' => 'Stanley', 'stock' => 35],
                ['name_ar' => 'cope صندوق عدة كشتبان زنك - 40 قطعة', 'name_en' => 'Zinc Socket Set - 40pc', 'sku' => 'SKT-040ZN', 'price' => 60.00, 'cost_price' => 35.00, 'unit' => 'طقم', 'brand' => 'Makita', 'stock' => 24],
                ['name_ar' => 'cope صندوق عدة كشتبان نحاس - 25 قطعة', 'name_en' => 'Brass Socket Set - 25pc', 'sku' => 'SKT-025BR', 'price' => 48.00, 'cost_price' => 28.00, 'unit' => 'طقم', 'brand' => 'Bosch', 'stock' => 30],
            ],
        ],

        // ── 33: Electrical Tools & Soldering ────────────────────────────
        [
            'category_id' => 33,
            'category_slug' => 'electrical-soldering-tools',
            'products' => [
                ['name_ar' => 'مكواة لحام 40 وات', 'name_en' => 'Soldering Iron 40W', 'sku' => 'SOL-040W', 'price' => 15.00, 'cost_price' => 8.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 50],
                ['name_ar' => 'مكواة لحام 60 وات', 'name_en' => 'Soldering Iron 60W', 'sku' => 'SOL-060W', 'price' => 22.00, 'cost_price' => 12.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 40],
                ['name_ar' => 'مكواة لحام 100 وات', 'name_en' => 'Soldering Iron 100W', 'sku' => 'SOL-100W', 'price' => 35.00, 'cost_price' => 20.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 25],
                ['name_ar' => 'مكواة لحام سيارة', 'name_en' => 'Car Soldering Iron', 'sku' => 'SOL-CAR1', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 30],
                ['name_ar' => 'جهاز لحام بلاستيك', 'name_en' => 'Plastic Welding Kit', 'sku' => 'SOL-PLAS1', 'price' => 45.00, 'cost_price' => 28.00, 'unit' => 'طقم', 'brand' => 'Bosch', 'stock' => 20],
                ['name_ar' => 'فيشة كهربائية عادية', 'name_en' => 'Electrical Plug Standard', 'sku' => 'ELE-PLG1', 'price' => 3.00, 'cost_price' => 1.50, 'unit' => 'قطعة', 'brand' => 'MK', 'stock' => 200],
                ['name_ar' => 'فيشة كهربائية م西路ة', 'name_en' => 'Electrical Plug Waterproof', 'sku' => 'ELE-PLG2', 'price' => 5.00, 'cost_price' => 2.80, 'unit' => 'قطعة', 'brand' => 'MK', 'stock' => 150],
                ['name_ar' => 'مقابس كهربائية عادية - 3 ثقوب', 'name_en' => 'Electrical Socket 3-Gang', 'sku' => 'ELE-SOK3', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'MK', 'stock' => 120],
                ['name_ar' => 'مقابس كهربائية عادية - 2 ثقب', 'name_en' => 'Electrical Socket 2-Gang', 'sku' => 'ELE-SOK2', 'price' => 6.00, 'cost_price' => 3.20, 'unit' => 'قطعة', 'brand' => 'MK', 'stock' => 140],
                ['name_ar' => 'مفتاح كهربائي عادي', 'name_en' => 'Electrical Switch Standard', 'sku' => 'ELE-SW1', 'price' => 4.00, 'cost_price' => 2.00, 'unit' => 'قطعة', 'brand' => 'MK', 'stock' => 180],
                ['name_ar' => 'مفتاح كهربائي ضوئي', 'name_en' => 'Light Indicator Switch', 'sku' => 'ELE-SW2', 'price' => 7.00, 'cost_price' => 3.80, 'unit' => 'قطعة', 'brand' => 'MK', 'stock' => 100],
                ['name_ar' => 'شريط تمديد كهربائي 3 متر', 'name_en' => 'Extension Cord 3m', 'sku' => 'ELE-EXT3', 'price' => 12.00, 'cost_price' => 6.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 80],
                ['name_ar' => 'شريط تمديد كهربائي 5 متر', 'name_en' => 'Extension Cord 5m', 'sku' => 'ELE-EXT5', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 60],
                ['name_ar' => 'شريط تمديد كهربائي 10 متر', 'name_en' => 'Extension Cord 10m', 'sku' => 'ELE-EXT10', 'price' => 28.00, 'cost_price' => 16.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 40],
                ['name_ar' => 'فرش كهربائية - 50 قطعة', 'name_en' => 'Wire Brush Set 50pc', 'sku' => 'ELE-WBR50', 'price' => 25.00, 'cost_price' => 14.00, 'unit' => 'طقم', 'brand' => 'Stanley', 'stock' => 35],
                ['name_ar' => 'قاطع أسلاك كهربائي', 'name_en' => 'Wire Cutter', 'sku' => 'ELE-WC1', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 70],
                ['name_ar' => 'فاصل أسلاك كهربائي', 'name_en' => 'Wire Stripper', 'sku' => 'ELE-WS1', 'price' => 10.00, 'cost_price' => 5.50, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 55],
                ['name_ar' => 'جهاز قياس كهربائي - فولتميتر', 'name_en' => 'Digital Multimeter', 'sku' => 'ELE-DMM1', 'price' => 35.00, 'cost_price' => 20.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 30],
                ['name_ar' => 'جهاز اختبار كهربائي - مulti', 'name_en' => 'Voltage Tester', 'sku' => 'ELE-VT1', 'price' => 12.00, 'cost_price' => 6.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 45],
                ['name_ar' => 'مغناطيس كهربائي - قوة عالية', 'name_en' => 'Electromagnet High Power', 'sku' => 'ELE-MAG1', 'price' => 20.00, 'cost_price' => 11.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 25],
                ['name_ar' => 'موصل كهربائي - 10 قطع', 'name_en' => 'Wire Connector Set 10pc', 'sku' => 'ELE-WCN10', 'price' => 8.00, 'cost_price' => 4.00, 'unit' => 'طقم', 'brand' => 'Stanley', 'stock' => 90],
                ['name_ar' => 'موصل كهربائي - 20 قطعة', 'name_en' => 'Wire Connector Set 20pc', 'sku' => 'ELE-WCN20', 'price' => 14.00, 'cost_price' => 7.50, 'unit' => 'طقم', 'brand' => 'Bosch', 'stock' => 60],
                ['name_ar' => 'عازل كهربائي - لف', 'name_en' => 'Electrical Insulation Tape', 'sku' => 'ELE-INS1', 'price' => 2.00, 'cost_price' => 1.00, 'unit' => 'لفة', 'brand' => '3M', 'stock' => 300],
                ['name_ar' => 'عازل كهربائي - م西路', 'name_en' => 'Waterproof Insulation Tape', 'sku' => 'ELE-INS2', 'price' => 3.00, 'cost_price' => 1.50, 'unit' => 'لفة', 'brand' => '3M', 'stock' => 250],
                ['name_ar' => 'جهاز قياس TDS مياه', 'name_en' => 'TDS Water Meter', 'sku' => 'ELE-TDS1', 'price' => 25.00, 'cost_price' => 14.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 20],
                ['name_ar' => 'جهاز قياس رطوبة', 'name_en' => 'Humidity Meter', 'sku' => 'ELE-HUM1', 'price' => 30.00, 'cost_price' => 17.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 18],
                ['name_ar' => 'مقاومة لحام - 100 قطعة', 'name_en' => 'Soldering Tip Set 100pc', 'sku' => 'SOL-TIP100', 'price' => 15.00, 'cost_price' => 8.00, 'unit' => 'طقم', 'brand' => 'Makita', 'stock' => 25],
                ['name_ar' => ' مكواة لحام هوائية متحركة', 'name_en' => 'Hot Air Soldering Station', 'sku' => 'SOL-HOT1', 'price' => 85.00, 'cost_price' => 50.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 12],
                ['name_ar' => 'مكواة لحام USB', 'name_en' => 'USB Soldering Iron', 'sku' => 'SOL-USB1', 'price' => 12.00, 'cost_price' => 6.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 35],
                ['name_ar' => 'مكواة لحام قابل للطي', 'name_en' => 'Foldable Soldering Iron', 'sku' => 'SOL-FLD1', 'price' => 20.00, 'cost_price' => 11.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 28],
            ],
        ],

        // ── 34: Paint Brushes & Painting Tools ──────────────────────────
        [
            'category_id' => 34,
            'category_slug' => 'paint-brushes-tools',
            'products' => [
                ['name_ar' => 'فرشاة دهان 2 بوصة', 'name_en' => 'Paint Brush 2"', 'sku' => 'PNT-BR2', 'price' => 4.00, 'cost_price' => 2.00, 'unit' => 'قطعة', 'brand' => 'Purdy', 'stock' => 150],
                ['name_ar' => 'فرشاة دهان 3 بوصة', 'name_en' => 'Paint Brush 3"', 'sku' => 'PNT-BR3', 'price' => 6.00, 'cost_price' => 3.00, 'unit' => 'قطعة', 'brand' => 'Purdy', 'stock' => 120],
                ['name_ar' => 'فرشاة دهان 4 بوصة', 'name_en' => 'Paint Brush 4"', 'sku' => 'PNT-BR4', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'Purdy', 'stock' => 100],
                ['name_ar' => 'فرشاة دهان 1 بوصة', 'name_en' => 'Paint Brush 1"', 'sku' => 'PNT-BR1', 'price' => 2.50, 'cost_price' => 1.20, 'unit' => 'قطعة', 'brand' => 'Wooster', 'stock' => 200],
                ['name_ar' => 'فرشاة دهان زاوية 2 بوصة', 'name_en' => 'Angled Paint Brush 2"', 'sku' => 'PNT-ABR2', 'price' => 5.00, 'cost_price' => 2.80, 'unit' => 'قطعة', 'brand' => 'Purdy', 'stock' => 90],
                ['name_ar' => ' بكرة دهان - صغير', 'name_en' => 'Paint Roller Small', 'sku' => 'PNT-RLS', 'price' => 5.00, 'cost_price' => 2.50, 'unit' => 'قطعة', 'brand' => 'Wooster', 'stock' => 130],
                ['name_ar' => ' بكرة دهان - وسط', 'name_en' => 'Paint Roller Medium', 'sku' => 'PNT-RLM', 'price' => 7.00, 'cost_price' => 3.80, 'unit' => 'قطعة', 'brand' => 'Purdy', 'stock' => 110],
                ['name_ar' => ' بكرة دهان - كبير', 'name_en' => 'Paint Roller Large', 'sku' => 'PNT-RLL', 'price' => 9.00, 'cost_price' => 5.00, 'unit' => 'قطعة', 'brand' => 'Wooster', 'stock' => 80],
                ['name_ar' => ' مغطاة دهان قماش', 'name_en' => 'Paint Roller Cover Flock', 'sku' => 'PNT-RCF', 'price' => 3.00, 'cost_price' => 1.50, 'unit' => 'قطعة', 'brand' => 'Purdy', 'stock' => 200],
                ['name_ar' => ' مغطاة دهان رغوة', 'name_en' => 'Paint Roller Cover Foam', 'sku' => 'PNT-RCO', 'price' => 2.50, 'cost_price' => 1.20, 'unit' => 'قطعة', 'brand' => 'Wooster', 'stock' => 180],
                ['name_ar' => 'علبة دهان 1 لتر', 'name_en' => 'Paint Tray 1L', 'sku' => 'PNT-TR1', 'price' => 4.00, 'cost_price' => 2.00, 'unit' => 'قطعة', 'brand' => 'Purdy', 'stock' => 100],
                ['name_ar' => 'علبة دهان 5 لتر', 'name_en' => 'Paint Tray 5L', 'sku' => 'PNT-TR5', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'Wooster', 'stock' => 60],
                ['name_ar' => 'علبة دهان بلاستيك - صغيرة', 'name_en' => 'Plastic Paint Tray Small', 'sku' => 'PNT-PTS', 'price' => 2.00, 'cost_price' => 1.00, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 250],
                ['name_ar' => 'علبة دهان بلاستيك - كبيرة', 'name_en' => 'Plastic Paint Tray Large', 'sku' => 'PNT-PTL', 'price' => 4.50, 'cost_price' => 2.50, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 150],
                ['name_ar' => 'عصا تمديد دهان 1 متر', 'name_en' => 'Paint Extension Pole 1m', 'sku' => 'PNT-EP1', 'price' => 10.00, 'cost_price' => 5.50, 'unit' => 'قطعة', 'brand' => 'Purdy', 'stock' => 40],
                ['name_ar' => 'عصا تمديد دهان 2 متر', 'name_en' => 'Paint Extension Pole 2m', 'sku' => 'PNT-EP2', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Wooster', 'stock' => 25],
                ['name_ar' => 'غطاء بلاستيك للحماية', 'name_en' => 'Plastic Drop Cloth', 'sku' => 'PNT-PDC', 'price' => 5.00, 'cost_price' => 2.80, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 200],
                ['name_ar' => 'غطاء قماش للحماية', 'name_en' => 'Canvas Drop Cloth', 'sku' => 'PNT-CDC', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 50],
                ['name_ar' => 'شريط عازل للدهان', 'name_en' => 'Masking Tape', 'sku' => 'PNT-MT1', 'price' => 3.00, 'cost_price' => 1.50, 'unit' => 'لفة', 'brand' => '3M', 'stock' => 300],
                ['name_ar' => 'شريط عازل عريض للدهان', 'name_en' => 'Wide Masking Tape', 'sku' => 'PNT-MT2', 'price' => 5.00, 'cost_price' => 2.80, 'unit' => 'لفة', 'brand' => '3M', 'stock' => 150],
                ['name_ar' => 'سكين دهان متعدد الاستخدامات', 'name_en' => 'Multi-Purpose Paint Scraper', 'sku' => 'PNT-SCR', 'price' => 6.00, 'cost_price' => 3.20, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 80],
                ['name_ar' => 'رشاش دهان يدوي', 'name_en' => 'Manual Paint Sprayer', 'sku' => 'PNT-SPR1', 'price' => 25.00, 'cost_price' => 14.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 20],
                ['name_ar' => 'رشاش دهان كهربائي', 'name_en' => 'Electric Paint Sprayer', 'sku' => 'PNT-SPR2', 'price' => 65.00, 'cost_price' => 38.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 12],
                ['name_ar' => 'فرشاة حائط واسعة', 'name_en' => 'Wide Wall Brush', 'sku' => 'PNT-WBR', 'price' => 10.00, 'cost_price' => 5.50, 'unit' => 'قطعة', 'brand' => 'Purdy', 'stock' => 45],
                ['name_ar' => 'فرشاة سقف', 'name_en' => 'Ceiling Brush', 'sku' => 'PNT-CBR', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'Wooster', 'stock' => 60],
                ['name_ar' => 'فرشاة دهان سيليكون', 'name_en' => 'Silicone Paint Brush', 'sku' => 'PNT-SBR', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Purdy', 'stock' => 35],
                ['name_ar' => 'فرشاة دهان نايلون', 'name_en' => 'Nylon Paint Brush', 'sku' => 'PNT-NBR', 'price' => 7.00, 'cost_price' => 3.80, 'unit' => 'قطعة', 'brand' => 'Wooster', 'stock' => 70],
                ['name_ar' => 'فرشاة دهان خنزير', 'name_en' => 'Bristle Paint Brush', 'sku' => 'PNT-BBR', 'price' => 9.00, 'cost_price' => 5.00, 'unit' => 'قطعة', 'brand' => 'Purdy', 'stock' => 50],
                ['name_ar' => 'فرشاة دهان بوليستر', 'name_en' => 'Polyester Paint Brush', 'sku' => 'PNT-PBR', 'price' => 6.50, 'cost_price' => 3.50, 'unit' => 'قطعة', 'brand' => 'Wooster', 'stock' => 85],
                ['name_ar' => 'فرشاة دهان متعددة الأحجام', 'name_en' => 'Multi-Size Paint Brush Set', 'sku' => 'PNT-MBR', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'طقم', 'brand' => 'Stanley', 'stock' => 40],
            ],
        ],

        // ── 35: Files & Saws ───────────────────────────────────────────
        [
            'category_id' => 35,
            'category_slug' => 'files-saws',
            'products' => [
                ['name_ar' => 'مبرد مستطيل 10 بوصة', 'name_en' => 'Flat File 10"', 'sku' => 'FIL-FL10', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 60],
                ['name_ar' => 'مبرد مستطيل 12 بوصة', 'name_en' => 'Flat File 12"', 'sku' => 'FIL-FL12', 'price' => 10.00, 'cost_price' => 5.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 45],
                ['name_ar' => 'مبرد نصف دائري 10 بوصة', 'name_en' => 'Half-Round File 10"', 'sku' => 'FIL-HR10', 'price' => 9.00, 'cost_price' => 5.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 50],
                ['name_ar' => 'مبرد دائري 8 بوصة', 'name_en' => 'Round File 8"', 'sku' => 'FIL-RD8', 'price' => 7.00, 'cost_price' => 3.80, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 55],
                ['name_ar' => 'مبرد مثلث 8 بوصة', 'name_en' => 'Triangular File 8"', 'sku' => 'FIL-TR8', 'price' => 7.50, 'cost_price' => 4.20, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 40],
                ['name_ar' => 'مبارد متنقلة 12 بوصة', 'name_en' => 'Hand Saw 12"', 'sku' => 'SAW-HS12', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 35],
                ['name_ar' => 'مبارد متنقلة 16 بوصة', 'name_en' => 'Hand Saw 16"', 'sku' => 'SAW-HS16', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 30],
                ['name_ar' => 'منشارة يدوية 20 بوصة', 'name_en' => 'Hacksaw 20"', 'sku' => 'SAW-HK20', 'price' => 12.00, 'cost_price' => 6.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 40],
                ['name_ar' => 'منشارة يدوية 24 بوصة', 'name_en' => 'Hacksaw 24"', 'sku' => 'SAW-HK24', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 28],
                ['name_ar' => 'منشارة كهربائية', 'name_en' => 'Circular Saw', 'sku' => 'SAW-CS1', 'price' => 85.00, 'cost_price' => 50.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 15],
                ['name_ar' => 'منشارة متحركة', 'name_en' => 'Reciprocating Saw', 'sku' => 'SAW-RS1', 'price' => 75.00, 'cost_price' => 44.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 12],
                ['name_ar' => 'شفرة منشارة 20 بوصة', 'name_en' => 'Hacksaw Blade 20"', 'sku' => 'SAW-BL20', 'price' => 3.00, 'cost_price' => 1.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 200],
                ['name_ar' => 'شفرة منشارة 24 بوصة', 'name_en' => 'Hacksaw Blade 24"', 'sku' => 'SAW-BL24', 'price' => 3.50, 'cost_price' => 1.80, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 180],
                ['name_ar' => 'شفرة منشارة حديد', 'name_en' => 'Metal Cutting Blade', 'sku' => 'SAW-MCL', 'price' => 5.00, 'cost_price' => 2.80, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 150],
                ['name_ar' => 'شفرة منشارة خشب', 'name_en' => 'Wood Cutting Blade', 'sku' => 'SAW-WCL', 'price' => 4.00, 'cost_price' => 2.20, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 160],
                ['name_ar' => 'مبارد جبس 14 بوصة', 'name_en' => 'Drywall Saw 14"', 'sku' => 'SAW-DS14', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 35],
                ['name_ar' => 'مبارد ثلج', 'name_en' => 'Pruning Saw', 'sku' => 'SAW-PS1', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 25],
                ['name_ar' => 'مبارد خشب ثابتة', 'name_en' => 'Fixed Wood Saw', 'sku' => 'SAW-FWS', 'price' => 20.00, 'cost_price' => 11.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 20],
                ['name_ar' => 'مبارد خشب قابلة للطي', 'name_en' => 'Folding Wood Saw', 'sku' => 'SAW-FDS', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 30],
                ['name_ar' => 'مبارد جبس كهربائية', 'name_en' => 'Electric Drywall Saw', 'sku' => 'SAW-EDS', 'price' => 55.00, 'cost_price' => 32.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 10],
                ['name_ar' => 'مبارد حديد كهربائية', 'name_en' => 'Electric Metal Saw', 'sku' => 'SAW-EMS', 'price' => 95.00, 'cost_price' => 55.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 8],
                ['name_ar' => 'مبارد خشب كهربائية', 'name_en' => 'Electric Wood Saw', 'sku' => 'SAW-EWS', 'price' => 110.00, 'cost_price' => 65.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 10],
                ['name_ar' => 'مبارد متعددة الاستخدامات', 'name_en' => 'Multi-Purpose Saw', 'sku' => 'SAW-MPS', 'price' => 35.00, 'cost_price' => 20.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 18],
                ['name_ar' => 'مبارد خشب خفيفة', 'name_en' => 'Lightweight Wood Saw', 'sku' => 'SAW-LWS', 'price' => 10.00, 'cost_price' => 5.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 40],
                ['name_ar' => 'مبارد حديد خفيفة', 'name_en' => 'Lightweight Metal Saw', 'sku' => 'SAW-LMS', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 35],
                ['name_ar' => 'مبارد خشب دقيقة', 'name_en' => 'Precision Wood Saw', 'sku' => 'SAW-PWS', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 22],
                ['name_ar' => 'مبارد حديد دقيقة', 'name_en' => 'Precision Metal Saw', 'sku' => 'SAW-PMS', 'price' => 20.00, 'cost_price' => 11.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 18],
                ['name_ar' => 'مبارد متعددة الزوايا', 'name_en' => 'Multi-Angle Saw', 'sku' => 'SAW-MAS', 'price' => 45.00, 'cost_price' => 26.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 12],
                ['name_ar' => 'مبارد خشب ألياف', 'name_en' => 'Fiber Wood Saw', 'sku' => 'SAW-FBS', 'price' => 22.00, 'cost_price' => 12.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 15],
                ['name_ar' => 'مبارد حديد أنابيب', 'name_en' => 'Pipe Metal Saw', 'sku' => 'SAW-PMS2', 'price' => 25.00, 'cost_price' => 14.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 20],
            ],
        ],

        // ── 36: Plastering & Finishing Tools ────────────────────────────
        [
            'category_id' => 36,
            'category_slug' => 'plastering-finishing-tools',
            'products' => [
                ['name_ar' => 'مسطرين جبس 12 بوصة', 'name_en' => 'Plastering Trowel 12"', 'sku' => 'PLS-TR12', 'price' => 12.00, 'cost_price' => 6.50, 'unit' => 'قطعة', 'brand' => 'Marshalltown', 'stock' => 50],
                ['name_ar' => 'مسطرين جبس 14 بوصة', 'name_en' => 'Plastering Trowel 14"', 'sku' => 'PLS-TR14', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'قطعة', 'brand' => 'Marshalltown', 'stock' => 40],
                ['name_ar' => 'مسطرين جبس 16 بوصة', 'name_en' => 'Plastering Trowel 16"', 'sku' => 'PLS-TR16', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 35],
                ['name_ar' => 'مسطرين جبس 18 بوصة', 'name_en' => 'Plastering Trowel 18"', 'sku' => 'PLS-TR18', 'price' => 22.00, 'cost_price' => 12.50, 'unit' => 'قطعة', 'brand' => 'Marshalltown', 'stock' => 25],
                ['name_ar' => 'مسطرين جبس 20 بوصة', 'name_en' => 'Plastering Trowel 20"', 'sku' => 'PLS-TR20', 'price' => 25.00, 'cost_price' => 14.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 20],
                ['name_ar' => 'مسطرين جبس مرن 14 بوصة', 'name_en' => 'Flexible Plastering Trowel 14"', 'sku' => 'PLS-FT14', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Marshalltown', 'stock' => 30],
                ['name_ar' => 'مسطرين جبس مرن 16 بوصة', 'name_en' => 'Flexible Plastering Trowel 16"', 'sku' => 'PLS-FT16', 'price' => 22.00, 'cost_price' => 12.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 25],
                ['name_ar' => 'مسطرين تشطيب 10 بوصة', 'name_en' => 'Finishing Trowel 10"', 'sku' => 'PLS-Fi10', 'price' => 10.00, 'cost_price' => 5.50, 'unit' => 'قطعة', 'brand' => 'Marshalltown', 'stock' => 45],
                ['name_ar' => 'مسطرين تشطيب 12 بوصة', 'name_en' => 'Finishing Trowel 12"', 'sku' => 'PLS-Fi12', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 35],
                ['name_ar' => 'مسطرين تشطيب 14 بوصة', 'name_en' => 'Finishing Trowel 14"', 'sku' => 'PLS-Fi14', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 30],
                ['name_ar' => 'مسطرين تشطيب 16 بوصة', 'name_en' => 'Finishing Trowel 16"', 'sku' => 'PLS-Fi16', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Marshalltown', 'stock' => 22],
                ['name_ar' => 'مسطرين جبس كركي 4 بوصة', 'name_en' => 'Corner Trowel 4"', 'sku' => 'PLS-CT4', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 40],
                ['name_ar' => 'مسطرين جبس كركي 6 بوصة', 'name_en' => 'Corner Trowel 6"', 'sku' => 'PLS-CT6', 'price' => 10.00, 'cost_price' => 5.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 35],
                ['name_ar' => 'مسطرين جبس كركي 8 بوصة', 'name_en' => 'Corner Trowel 8"', 'sku' => 'PLS-CT8', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Marshalltown', 'stock' => 28],
                ['name_ar' => 'مسح جبس 12 بوصة', 'name_en' => 'Plaster Float 12"', 'sku' => 'PLS-FL12', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 45],
                ['name_ar' => 'مسح جبس 14 بوصة', 'name_en' => 'Plaster Float 14"', 'sku' => 'PLS-FL14', 'price' => 10.00, 'cost_price' => 5.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 38],
                ['name_ar' => 'مسح جبس 16 بوصة', 'name_en' => 'Plaster Float 16"', 'sku' => 'PLS-FL16', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Marshalltown', 'stock' => 30],
                ['name_ar' => 'مسح جبس خرساني', 'name_en' => 'Concrete Float', 'sku' => 'PLS-CFL', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 25],
                ['name_ar' => 'مسح جبس خشبي', 'name_en' => 'Wood Float', 'sku' => 'PLS-WFL', 'price' => 10.00, 'cost_price' => 5.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 35],
                ['name_ar' => 'مسح جبس مطاطي', 'name_en' => 'Rubber Float', 'sku' => 'PLS-RFL', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Marshalltown', 'stock' => 30],
                ['name_ar' => 'خزان خلط جبس - 20 لتر', 'name_en' => 'Plaster Mixing Bucket 20L', 'sku' => 'PLS-BK20', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 60],
                ['name_ar' => 'خزان خلط جبس - 40 لتر', 'name_en' => 'Plaster Mixing Bucket 40L', 'sku' => 'PLS-BK40', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 40],
                ['name_ar' => 'رشاش جبس كهربائي', 'name_en' => 'Electric Plaster Sprayer', 'sku' => 'PLS-SPR', 'price' => 120.00, 'cost_price' => 70.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 8],
                ['name_ar' => 'خلاط جبس كهربائي', 'name_en' => 'Electric Plaster Mixer', 'sku' => 'PLS-MIX', 'price' => 65.00, 'cost_price' => 38.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 12],
                ['name_ar' => 'خلاط جبس يدوي', 'name_en' => 'Manual Plaster Mixer', 'sku' => 'PLS-MXM', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 25],
                ['name_ar' => 'رشاش جبس يدوي', 'name_en' => 'Manual Plaster Sprayer', 'sku' => 'PLS-SPM', 'price' => 25.00, 'cost_price' => 14.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 15],
                ['name_ar' => 'مسطرة جبس 2 متر', 'name_en' => 'Plastering Straight Edge 2m', 'sku' => 'PLS-SE2', 'price' => 30.00, 'cost_price' => 17.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 18],
                ['name_ar' => 'مسطرة جبس 2.5 متر', 'name_en' => 'Plastering Straight Edge 2.5m', 'sku' => 'PLS-SE25', 'price' => 35.00, 'cost_price' => 20.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 12],
                ['name_ar' => 'مسطرة جبس 3 متر', 'name_en' => 'Plastering Straight Edge 3m', 'sku' => 'PLS-SE3', 'price' => 42.00, 'cost_price' => 24.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 10],
                ['name_ar' => 'مسطرة جبس 1.5 متر', 'name_en' => 'Plastering Straight Edge 1.5m', 'sku' => 'PLS-SE15', 'price' => 22.00, 'cost_price' => 12.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 22],
            ],
        ],

        // ── 37: Plumbing Tools ─────────────────────────────────────────
        [
            'category_id' => 37,
            'category_slug' => 'plumbing-tools',
            'products' => [
                ['name_ar' => 'مفتاح ربط أنابيب 10 بوصة', 'name_en' => 'Pipe Wrench 10"', 'sku' => 'PLB-PW10', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'قطعة', 'brand' => 'Ridgid', 'stock' => 40],
                ['name_ar' => 'مفتاح ربط أنابيب 12 بوصة', 'name_en' => 'Pipe Wrench 12"', 'sku' => 'PLB-PW12', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Ridgid', 'stock' => 35],
                ['name_ar' => 'مفتاح ربط أنابيب 14 بوصة', 'name_en' => 'Pipe Wrench 14"', 'sku' => 'PLB-PW14', 'price' => 22.00, 'cost_price' => 12.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 30],
                ['name_ar' => 'مفتاح ربط أنابيب 18 بوصة', 'name_en' => 'Pipe Wrench 18"', 'sku' => 'PLB-PW18', 'price' => 28.00, 'cost_price' => 16.00, 'unit' => 'قطعة', 'brand' => 'Ridgid', 'stock' => 20],
                ['name_ar' => 'مفتاح ربط أنابيب 24 بوصة', 'name_en' => 'Pipe Wrench 24"', 'sku' => 'PLB-PW24', 'price' => 38.00, 'cost_price' => 22.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 12],
                ['name_ar' => 'مقص قص أنابيب بلاستيك', 'name_en' => 'PVC Pipe Cutter', 'sku' => 'PLB-PVC1', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Ridgid', 'stock' => 45],
                ['name_ar' => 'مقص قص أنابيب حديد', 'name_en' => 'Metal Pipe Cutter', 'sku' => 'PLB-MPC1', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 25],
                ['name_ar' => 'مقص قص أنابيب نحاس', 'name_en' => 'Copper Pipe Cutter', 'sku' => 'PLB-CPC1', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'قطعة', 'brand' => 'Ridgid', 'stock' => 30],
                ['name_ar' => 'مقص قص أنابيب كهربائي', 'name_en' => 'Electric Pipe Cutter', 'sku' => 'PLB-EPC1', 'price' => 85.00, 'cost_price' => 50.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 10],
                ['name_ar' => 'ملبمة أنابيب نحاس', 'name_en' => 'Copper Pipe Flaring Tool', 'sku' => 'PLB-CPF1', 'price' => 25.00, 'cost_price' => 14.00, 'unit' => 'قطعة', 'brand' => 'Ridgid', 'stock' => 20],
                ['name_ar' => 'ملبمة أنابيب حديد', 'name_en' => 'Metal Pipe Threading Tool', 'sku' => 'PLB-MPT1', 'price' => 45.00, 'cost_price' => 26.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 12],
                ['name_ar' => 'ملبمة أنابيب نحاس كهربائية', 'name_en' => 'Electric Copper Pipe Threading Tool', 'sku' => 'PLB-ECPT', 'price' => 110.00, 'cost_price' => 65.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 6],
                ['name_ar' => 'أداة تقوية أنابيب بلاستيك', 'name_en' => 'PVC Pipe Deburring Tool', 'sku' => 'PLB-PVD1', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'Ridgid', 'stock' => 40],
                ['name_ar' => 'أداة لحام أنابيب بلاستيك', 'name_en' => 'PVC Pipe Welding Tool', 'sku' => 'PLB-PVW1', 'price' => 65.00, 'cost_price' => 38.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 8],
                ['name_ar' => 'أداة قطع أنابيب متعددة', 'name_en' => 'Multi-Purpose Pipe Cutter', 'sku' => 'PLB-MPC2', 'price' => 30.00, 'cost_price' => 17.00, 'unit' => 'قطعة', 'brand' => 'Ridgid', 'stock' => 15],
                ['name_ar' => 'مفتاح شنط أنابيب', 'name_en' => 'Basin Wrench', 'sku' => 'PLB-BW1', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 35],
                ['name_ar' => 'مفتاح شنط أنابيب قابل للطي', 'name_en' => 'Folding Basin Wrench', 'sku' => 'PLB-FBW', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 25],
                ['name_ar' => 'مفتاح ربط أنابيب مزدوج', 'name_en' => 'Double Pipe Wrench', 'sku' => 'PLB-DPW', 'price' => 25.00, 'cost_price' => 14.00, 'unit' => 'قطعة', 'brand' => 'Ridgid', 'stock' => 18],
                ['name_ar' => 'مفتاح ربط أنابيب فولاذي', 'name_en' => 'Steel Pipe Wrench', 'sku' => 'PLB-SPW', 'price' => 20.00, 'cost_price' => 11.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 28],
                ['name_ar' => 'مفتاح ربط أنابيب ألومنيوم', 'name_en' => 'Aluminium Pipe Wrench', 'sku' => 'PLB-APW', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 22],
                ['name_ar' => 'مفتاح ربط أنابيب ستانلس', 'name_en' => 'Stainless Pipe Wrench', 'sku' => 'PLB-SSPW', 'price' => 22.00, 'cost_price' => 12.50, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 15],
                ['name_ar' => 'مفتاح ربط أنابيب بلاستيك', 'name_en' => 'Plastic Pipe Wrench', 'sku' => 'PLB-PPW', 'price' => 10.00, 'cost_price' => 5.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 40],
                ['name_ar' => 'مفتاح ربط أنابيب مطاطي', 'name_en' => 'Rubber Pipe Wrench', 'sku' => 'PLB-RPW', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 30],
                ['name_ar' => 'مفتاح ربط أنابيب خشبي', 'name_en' => 'Wooden Pipe Wrench', 'sku' => 'PLB-WPW', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 50],
                ['name_ar' => 'مفتاح ربط أنابيب كربون', 'name_en' => 'Carbon Pipe Wrench', 'sku' => 'PLB-CPW', 'price' => 16.00, 'cost_price' => 9.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 25],
                ['name_ar' => 'مفتاح ربط أنابيب تيتانيوم', 'name_en' => 'Titanium Pipe Wrench', 'sku' => 'PLB-TPW', 'price' => 35.00, 'cost_price' => 20.00, 'unit' => 'قطعة', 'brand' => 'Ridgid', 'stock' => 8],
                ['name_ar' => 'مفتاح ربط أنابيب زنك', 'name_en' => 'Zinc Pipe Wrench', 'sku' => 'PLB-ZPW', 'price' => 14.00, 'cost_price' => 7.80, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 32],
                ['name_ar' => 'مفتاح ربط أنابيب نحاس', 'name_en' => 'Brass Pipe Wrench', 'sku' => 'PLB-BPW', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 20],
                ['name_ar' => 'مفتاح ربط أنابيب كروم', 'name_en' => 'Chrome Pipe Wrench', 'sku' => 'PLB-CPW2', 'price' => 20.00, 'cost_price' => 11.50, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 18],
                ['name_ar' => 'مفتاح ربط أنابيب مطلي', 'name_en' => 'Plated Pipe Wrench', 'sku' => 'PLB-PPW2', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 28],
            ],
        ],

        // ── 38: Hammers & Chisels ─────────────────────────────────────
        [
            'category_id' => 38,
            'category_slug' => 'hammers-chisels',
            'products' => [
                ['name_ar' => 'مطرقة حديد 500 جرام', 'name_en' => 'Claw Hammer 500g', 'sku' => 'HAM-CH500', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 50],
                ['name_ar' => 'مطرقة حديد 750 جرام', 'name_en' => 'Claw Hammer 750g', 'sku' => 'HAM-CH750', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 40],
                ['name_ar' => 'مطرقة حديد 1 كجم', 'name_en' => 'Claw Hammer 1kg', 'sku' => 'HAM-CH1K', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 35],
                ['name_ar' => 'مطرقة حديد 2 كجم', 'name_en' => 'Claw Hammer 2kg', 'sku' => 'HAM-CH2K', 'price' => 22.00, 'cost_price' => 12.50, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 25],
                ['name_ar' => 'مطرقة حديد مطاطي 500 جرام', 'name_en' => 'Rubber Mallet 500g', 'sku' => 'HAM-RM500', 'price' => 10.00, 'cost_price' => 5.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 45],
                ['name_ar' => 'مطرقة حديد مطاطي 1 كجم', 'name_en' => 'Rubber Mallet 1kg', 'sku' => 'HAM-RM1K', 'price' => 14.00, 'cost_price' => 7.80, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 35],
                ['name_ar' => 'مطرقة حديد مطاطي 2 كجم', 'name_en' => 'Rubber Mallet 2kg', 'sku' => 'HAM-RM2K', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 25],
                ['name_ar' => 'مطرقة خشبية 500 جرام', 'name_en' => 'Wooden Mallet 500g', 'sku' => 'HAM-WM500', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 55],
                ['name_ar' => 'مطرقة خشبية 1 كجم', 'name_en' => 'Wooden Mallet 1kg', 'sku' => 'HAM-WM1K', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 40],
                ['name_ar' => 'مطرقة حديد مطاطي 3 كجم', 'name_en' => 'Rubber Mallet 3kg', 'sku' => 'HAM-RM3K', 'price' => 22.00, 'cost_price' => 12.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 18],
                ['name_ar' => 'مطرقة حديد 3 كجم', 'name_en' => 'Claw Hammer 3kg', 'sku' => 'HAM-CH3K', 'price' => 28.00, 'cost_price' => 16.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 15],
                ['name_ar' => 'مطرقة حديد مركبة 1 كجم', 'name_en' => 'Ball Peen Hammer 1kg', 'sku' => 'HAM-BP1K', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 30],
                ['name_ar' => 'مطرقة حديد مركبة 500 جرام', 'name_en' => 'Ball Peen Hammer 500g', 'sku' => 'HAM-BP500', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 38],
                ['name_ar' => 'مطرقة حديد مركبة 2 كجم', 'name_en' => 'Ball Peen Hammer 2kg', 'sku' => 'HAM-BP2K', 'price' => 20.00, 'cost_price' => 11.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 22],
                ['name_ar' => 'مطرقة حديد مطلي 500 جرام', 'name_en' => 'Plated Hammer 500g', 'sku' => 'HAM-PL500', 'price' => 10.00, 'cost_price' => 5.50, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 45],
                ['name_ar' => 'مطرقة حديد مطلي 1 كجم', 'name_en' => 'Plated Hammer 1kg', 'sku' => 'HAM-PL1K', 'price' => 14.00, 'cost_price' => 7.80, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 35],
                ['name_ar' => 'مطرقة حديد مطلي 2 كجم', 'name_en' => 'Plated Hammer 2kg', 'sku' => 'HAM-PL2K', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 25],
                ['name_ar' => 'مطرقة حديد مطلي 3 كجم', 'name_en' => 'Plated Hammer 3kg', 'sku' => 'HAM-PL3K', 'price' => 22.00, 'cost_price' => 12.50, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 18],
                ['name_ar' => 'مطرقة حديد كروم 500 جرام', 'name_en' => 'Chrome Hammer 500g', 'sku' => 'HAM-CR500', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 30],
                ['name_ar' => 'مطرقة حديد كروم 1 كجم', 'name_en' => 'Chrome Hammer 1kg', 'sku' => 'HAM-CR1K', 'price' => 16.00, 'cost_price' => 9.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 28],
                ['name_ar' => 'مطرقة حديد كروم 2 كجم', 'name_en' => 'Chrome Hammer 2kg', 'sku' => 'HAM-CR2K', 'price' => 20.00, 'cost_price' => 11.50, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 20],
                ['name_ar' => 'مطرقة حديد كروم 3 كجم', 'name_en' => 'Chrome Hammer 3kg', 'sku' => 'HAM-CR3K', 'price' => 25.00, 'cost_price' => 14.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 15],
                ['name_ar' => 'مطرقة حديد ستانلس 500 جرام', 'name_en' => 'Stainless Hammer 500g', 'sku' => 'HAM-SS500', 'price' => 14.00, 'cost_price' => 7.80, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 25],
                ['name_ar' => 'مطرقة حديد ستانلس 1 كجم', 'name_en' => 'Stainless Hammer 1kg', 'sku' => 'HAM-SS1K', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 22],
                ['name_ar' => 'مطرقة حديد ستانلس 2 كجم', 'name_en' => 'Stainless Hammer 2kg', 'sku' => 'HAM-SS2K', 'price' => 22.00, 'cost_price' => 12.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 15],
                ['name_ar' => 'مطرقة حديد ستانلس 3 كجم', 'name_en' => 'Stainless Hammer 3kg', 'sku' => 'HAM-SS3K', 'price' => 28.00, 'cost_price' => 16.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 10],
                ['name_ar' => 'مطرقة حديد نحاس 500 جرام', 'name_en' => 'Brass Hammer 500g', 'sku' => 'HAM-BR500', 'price' => 16.00, 'cost_price' => 9.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 20],
                ['name_ar' => 'مطرقة حديد نحاس 1 كجم', 'name_en' => 'Brass Hammer 1kg', 'sku' => 'HAM-BR1K', 'price' => 20.00, 'cost_price' => 11.50, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 15],
                ['name_ar' => 'مطرقة حديد نحاس 2 كجم', 'name_en' => 'Brass Hammer 2kg', 'sku' => 'HAM-BR2K', 'price' => 25.00, 'cost_price' => 14.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 12],
                ['name_ar' => 'مطرقة حديد نحاس 3 كجم', 'name_en' => 'Brass Hammer 3kg', 'sku' => 'HAM-BR3K', 'price' => 30.00, 'cost_price' => 17.00, 'unit' => 'قطعة', 'brand' => 'Makita', 'stock' => 8],
            ],
        ],

        // ── 39: Ladders & Lifting Equipment ────────────────────────────
        [
            'category_id' => 39,
            'category_slug' => 'ladders-lifting-equipment',
            'products' => [
                ['name_ar' => 'سلالم خشب 3 درجات', 'name_en' => 'Wooden Step Ladder 3-Step', 'sku' => 'LAD-WS3', 'price' => 35.00, 'cost_price' => 20.00, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 30],
                ['name_ar' => 'سلالم خشب 4 درجات', 'name_en' => 'Wooden Step Ladder 4-Step', 'sku' => 'LAD-WS4', 'price' => 45.00, 'cost_price' => 26.00, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 25],
                ['name_ar' => 'سلالم خشب 5 درجات', 'name_en' => 'Wooden Step Ladder 5-Step', 'sku' => 'LAD-WS5', 'price' => 55.00, 'cost_price' => 32.00, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 20],
                ['name_ar' => 'سلالم خشب 6 درجات', 'name_en' => 'Wooden Step Ladder 6-Step', 'sku' => 'LAD-WS6', 'price' => 65.00, 'cost_price' => 38.00, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 15],
                ['name_ar' => 'سلالم حديد 3 درجات', 'name_en' => 'Steel Step Ladder 3-Step', 'sku' => 'LAD-SS3', 'price' => 40.00, 'cost_price' => 23.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 28],
                ['name_ar' => 'سلالم حديد 4 درجات', 'name_en' => 'Steel Step Ladder 4-Step', 'sku' => 'LAD-SS4', 'price' => 50.00, 'cost_price' => 29.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 22],
                ['name_ar' => 'سلالم حديد 5 درجات', 'name_en' => 'Steel Step Ladder 5-Step', 'sku' => 'LAD-SS5', 'price' => 60.00, 'cost_price' => 35.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 18],
                ['name_ar' => 'سلالم حديد 6 درجات', 'name_en' => 'Steel Step Ladder 6-Step', 'sku' => 'LAD-SS6', 'price' => 70.00, 'cost_price' => 40.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 15],
                ['name_ar' => 'سلالم ألومنيوم 3 درجات', 'name_en' => 'Aluminium Step Ladder 3-Step', 'sku' => 'LAD-AS3', 'price' => 45.00, 'cost_price' => 26.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 25],
                ['name_ar' => 'سلالم ألومنيوم 4 درجات', 'name_en' => 'Aluminium Step Ladder 4-Step', 'sku' => 'LAD-AS4', 'price' => 55.00, 'cost_price' => 32.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 20],
                ['name_ar' => 'سلالم ألومنيوم 5 درجات', 'name_en' => 'Aluminium Step Ladder 5-Step', 'sku' => 'LAD-AS5', 'price' => 65.00, 'cost_price' => 38.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 15],
                ['name_ar' => 'سلالم ألومنيوم 6 درجات', 'name_en' => 'Aluminium Step Ladder 6-Step', 'sku' => 'LAD-AS6', 'price' => 75.00, 'cost_price' => 43.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 12],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 3 درجات', 'name_en' => 'Folding Aluminium Ladder 3-Step', 'sku' => 'LAD-FAL3', 'price' => 50.00, 'cost_price' => 29.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 20],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 4 درجات', 'name_en' => 'Folding Aluminium Ladder 4-Step', 'sku' => 'LAD-FAL4', 'price' => 60.00, 'cost_price' => 35.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 15],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 5 درجات', 'name_en' => 'Folding Aluminium Ladder 5-Step', 'sku' => 'LAD-FAL5', 'price' => 70.00, 'cost_price' => 40.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 12],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 6 درجات', 'name_en' => 'Folding Aluminium Ladder 6-Step', 'sku' => 'LAD-FAL6', 'price' => 80.00, 'cost_price' => 46.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 10],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 7 درجات', 'name_en' => 'Folding Aluminium Ladder 7-Step', 'sku' => 'LAD-FAL7', 'price' => 90.00, 'cost_price' => 52.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 8],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 8 درجات', 'name_en' => 'Folding Aluminium Ladder 8-Step', 'sku' => 'LAD-FAL8', 'price' => 100.00, 'cost_price' => 58.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 6],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 9 درجات', 'name_en' => 'Folding Aluminium Ladder 9-Step', 'sku' => 'LAD-FAL9', 'price' => 110.00, 'cost_price' => 64.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 5],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 10 درجات', 'name_en' => 'Folding Aluminium Ladder 10-Step', 'sku' => 'LAD-FAL10', 'price' => 120.00, 'cost_price' => 70.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 4],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 11 درجات', 'name_en' => 'Folding Aluminium Ladder 11-Step', 'sku' => 'LAD-FAL11', 'price' => 130.00, 'cost_price' => 75.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 3],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 12 درجات', 'name_en' => 'Folding Aluminium Ladder 12-Step', 'sku' => 'LAD-FAL12', 'price' => 140.00, 'cost_price' => 81.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 2],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 13 درجات', 'name_en' => 'Folding Aluminium Ladder 13-Step', 'sku' => 'LAD-FAL13', 'price' => 150.00, 'cost_price' => 87.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 2],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 14 درجات', 'name_en' => 'Folding Aluminium Ladder 14-Step', 'sku' => 'LAD-FAL14', 'price' => 160.00, 'cost_price' => 93.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 1],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 15 درجات', 'name_en' => 'Folding Aluminium Ladder 15-Step', 'sku' => 'LAD-FAL15', 'price' => 170.00, 'cost_price' => 99.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 1],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 16 درجات', 'name_en' => 'Folding Aluminium Ladder 16-Step', 'sku' => 'LAD-FAL16', 'price' => 180.00, 'cost_price' => 104.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 1],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 17 درجات', 'name_en' => 'Folding Aluminium Ladder 17-Step', 'sku' => 'LAD-FAL17', 'price' => 190.00, 'cost_price' => 110.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 1],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 18 درجات', 'name_en' => 'Folding Aluminium Ladder 18-Step', 'sku' => 'LAD-FAL18', 'price' => 200.00, 'cost_price' => 116.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 1],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 19 درجات', 'name_en' => 'Folding Aluminium Ladder 19-Step', 'sku' => 'LAD-FAL19', 'price' => 210.00, 'cost_price' => 122.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 1],
                ['name_ar' => 'سلالم ألومنيوم قابل للطي 20 درجات', 'name_en' => 'Folding Aluminium Ladder 20-Step', 'sku' => 'LAD-FAL20', 'price' => 220.00, 'cost_price' => 128.00, 'unit' => 'قطعة', 'brand' => 'Werner', 'stock' => 1],
            ],
        ],

        // ── 40: Ropes, Chains & Straps ────────────────────────────────
        [
            'category_id' => 40,
            'category_slug' => 'ropes-chains-straps',
            'products' => [
                ['name_ar' => 'حبل نايلون 6 مم - 30 متر', 'name_en' => 'Nylon Rope 6mm - 30m', 'sku' => 'ROP-N6M30', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 80],
                ['name_ar' => 'حبل نايلون 8 مم - 30 متر', 'name_en' => 'Nylon Rope 8mm - 30m', 'sku' => 'ROP-N8M30', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 60],
                ['name_ar' => 'حبل نايلون 10 مم - 30 متر', 'name_en' => 'Nylon Rope 10mm - 30m', 'sku' => 'ROP-N10M30', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 45],
                ['name_ar' => 'حبل نايلون 12 مم - 30 متر', 'name_en' => 'Nylon Rope 12mm - 30m', 'sku' => 'ROP-N12M30', 'price' => 22.00, 'cost_price' => 12.50, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 35],
                ['name_ar' => 'حبل بولي إيثيلين 6 مم - 30 متر', 'name_en' => 'Polyethylene Rope 6mm - 30m', 'sku' => 'ROP-PE6M30', 'price' => 6.00, 'cost_price' => 3.20, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 90],
                ['name_ar' => 'حبل بولي إيثيلين 8 مم - 30 متر', 'name_en' => 'Polyethylene Rope 8mm - 30m', 'sku' => 'ROP-PE8M30', 'price' => 9.00, 'cost_price' => 5.00, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 70],
                ['name_ar' => 'حبل بولي إيثيلين 10 مم - 30 متر', 'name_en' => 'Polyethylene Rope 10mm - 30m', 'sku' => 'ROP-PE10M30', 'price' => 14.00, 'cost_price' => 7.80, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 50],
                ['name_ar' => 'حبال فولاذ 3 مم - 30 متر', 'name_en' => 'Steel Wire Rope 3mm - 30m', 'sku' => 'ROP-SW3M30', 'price' => 25.00, 'cost_price' => 14.00, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 40],
                ['name_ar' => 'حبال فولاذ 5 مم - 30 متر', 'name_en' => 'Steel Wire Rope 5mm - 30m', 'sku' => 'ROP-SW5M30', 'price' => 45.00, 'cost_price' => 26.00, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 25],
                ['name_ar' => 'حبال فولاذ 8 مم - 30 متر', 'name_en' => 'Steel Wire Rope 8mm - 30m', 'sku' => 'ROP-SW8M30', 'price' => 75.00, 'cost_price' => 43.00, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 15],
                ['name_ar' => 'سلسلة حديد 6 مم - 3 متر', 'name_en' => 'Steel Chain 6mm - 3m', 'sku' => 'ROP-CH6', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 40],
                ['name_ar' => 'سلسلة حديد 8 مم - 3 متر', 'name_en' => 'Steel Chain 8mm - 3m', 'sku' => 'ROP-CH8', 'price' => 22.00, 'cost_price' => 12.50, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 30],
                ['name_ar' => 'سلسلة حديد 10 مم - 3 متر', 'name_en' => 'Steel Chain 10mm - 3m', 'sku' => 'ROP-CH10', 'price' => 32.00, 'cost_price' => 18.00, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 22],
                ['name_ar' => 'سلسلة حديد 12 مم - 3 متر', 'name_en' => 'Steel Chain 12mm - 3m', 'sku' => 'ROP-CH12', 'price' => 45.00, 'cost_price' => 26.00, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 15],
                ['name_ar' => 'شريط حمولة 1 طن - 5 متر', 'name_en' => 'Cargo Strap 1T - 5m', 'sku' => 'ROP-CS1T5', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 50],
                ['name_ar' => 'شريط حمولة 2 طن - 5 متر', 'name_en' => 'Cargo Strap 2T - 5m', 'sku' => 'ROP-CS2T5', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 35],
                ['name_ar' => 'شريط حمولة 3 طن - 5 متر', 'name_en' => 'Cargo Strap 3T - 5m', 'sku' => 'ROP-CS3T5', 'price' => 25.00, 'cost_price' => 14.00, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 25],
                ['name_ar' => 'شريط حمولة 5 طن - 5 متر', 'name_en' => 'Cargo Strap 5T - 5m', 'sku' => 'ROP-CS5T5', 'price' => 35.00, 'cost_price' => 20.00, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 15],
                ['name_ar' => 'مربط شاحنة 10 طن - 8 متر', 'name_en' => 'Truck Tie-Down 10T - 8m', 'sku' => 'ROP-TD10', 'price' => 55.00, 'cost_price' => 32.00, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 10],
                ['name_ar' => 'مربط شاحنة 5 طن - 6 متر', 'name_en' => 'Truck Tie-Down 5T - 6m', 'sku' => 'ROP-TD5', 'price' => 38.00, 'cost_price' => 22.00, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 18],
                ['name_ar' => 'حبل مطاطي 10 مم - 30 متر', 'name_en' => 'Rubber Bungee Rope 10mm - 30m', 'sku' => 'ROP-BR10', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 40],
                ['name_ar' => 'حبل مطاطي 12 مم - 30 متر', 'name_en' => 'Rubber Bungee Rope 12mm - 30m', 'sku' => 'ROP-BR12', 'price' => 20.00, 'cost_price' => 11.50, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 30],
                ['name_ar' => 'حبل شد 10 مم - 30 متر', 'name_en' => 'Tension Rope 10mm - 30m', 'sku' => 'ROP-TR10', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 45],
                ['name_ar' => 'حبل شد 12 مم - 30 متر', 'name_en' => 'Tension Rope 12mm - 30m', 'sku' => 'ROP-TR12', 'price' => 16.00, 'cost_price' => 9.00, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 35],
                ['name_ar' => 'حلقة فولاذ 6 مم', 'name_en' => 'Steel Shackle 6mm', 'sku' => 'ROP-SH6', 'price' => 5.00, 'cost_price' => 2.80, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 60],
                ['name_ar' => 'حلقة فولاذ 10 مم', 'name_en' => 'Steel Shackle 10mm', 'sku' => 'ROP-SH10', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 40],
                ['name_ar' => 'حلقة فولاذ 12 مم', 'name_en' => 'Steel Shackle 12mm', 'sku' => 'ROP-SH12', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 30],
                ['name_ar' => 'م Rozar حلقة ربط فولاذ', 'name_en' => 'Steel Ring Connector', 'sku' => 'ROP-RC1', 'price' => 4.00, 'cost_price' => 2.20, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 80],
                ['name_ar' => 'م Rozar م Guidance ربط فولاذي', 'name_en' => 'Steel Guide Ring', 'sku' => 'ROP-GR1', 'price' => 6.00, 'cost_price' => 3.50, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 50],
                ['name_ar' => 'م Rozar حلقة ربط نحاس', 'name_en' => 'Brass Ring Connector', 'sku' => 'ROP-BRC1', 'price' => 7.00, 'cost_price' => 4.00, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 35],
            ],
        ],

        // ── 41: Miscellaneous Tools & Supplies ─────────────────────────
        [
            'category_id' => 41,
            'category_slug' => 'misc-tools-supplies',
            'products' => [
                ['name_ar' => 'قفازات عمل قطن - 12 زوج', 'name_en' => 'Cotton Work Gloves - 12 Pair', 'sku' => 'MSC-WG12', 'price' => 15.00, 'cost_price' => 8.50, 'unit' => 'دزينة', 'brand' => 'Generic', 'stock' => 100],
                ['name_ar' => 'قفازات عمل جلد - 12 زوج', 'name_en' => 'Leather Work Gloves - 12 Pair', 'sku' => 'MSC-LG12', 'price' => 25.00, 'cost_price' => 14.00, 'unit' => 'دزينة', 'brand' => 'Generic', 'stock' => 60],
                ['name_ar' => 'قفازات عمل مطاطي - 12 زوج', 'name_en' => 'Rubber Work Gloves - 12 Pair', 'sku' => 'MSC-RG12', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'دزينة', 'brand' => 'Generic', 'stock' => 80],
                ['name_ar' => 'نظارات حماية شفافة', 'name_en' => 'Clear Safety Goggles', 'sku' => 'MSC-CG1', 'price' => 3.00, 'cost_price' => 1.50, 'unit' => 'قطعة', 'brand' => '3M', 'stock' => 150],
                ['name_ar' => 'نظارات حماية داكنة', 'name_en' => 'Dark Safety Goggles', 'sku' => 'MSC-DG1', 'price' => 3.50, 'cost_price' => 1.80, 'unit' => 'قطعة', 'brand' => '3M', 'stock' => 120],
                ['name_ar' => 'سماعات حماية', 'name_en' => 'Ear Protection Muffs', 'sku' => 'MSC-EP1', 'price' => 5.00, 'cost_price' => 2.80, 'unit' => 'قطعة', 'brand' => '3M', 'stock' => 100],
                ['name_ar' => 'كمامات حماية - 50 قطعة', 'name_en' => 'Face Masks - 50pc', 'sku' => 'MSC-FM50', 'price' => 10.00, 'cost_price' => 5.50, 'unit' => 'علبة', 'brand' => '3M', 'stock' => 200],
                ['name_ar' => 'مسطرة قياس 3 متر', 'name_en' => 'Measuring Tape 3m', 'sku' => 'MSC-MT3', 'price' => 8.00, 'cost_price' => 4.50, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 80],
                ['name_ar' => 'مسطرة قياس 5 متر', 'name_en' => 'Measuring Tape 5m', 'sku' => 'MSC-MT5', 'price' => 12.00, 'cost_price' => 6.80, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 60],
                ['name_ar' => 'مسطرة قياس 7.5 متر', 'name_en' => 'Measuring Tape 7.5m', 'sku' => 'MSC-MT75', 'price' => 18.00, 'cost_price' => 10.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 40],
                ['name_ar' => 'مسطرة قياس 10 متر', 'name_en' => 'Measuring Tape 10m', 'sku' => 'MSC-MT10', 'price' => 25.00, 'cost_price' => 14.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 30],
                ['name_ar' => 'مسطرة قياس 30 متر', 'name_en' => 'Measuring Tape 30m', 'sku' => 'MSC-MT30', 'price' => 55.00, 'cost_price' => 32.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 15],
                ['name_ar' => 'مسطرة قياس 50 متر', 'name_en' => 'Measuring Tape 50m', 'sku' => 'MSC-MT50', 'price' => 75.00, 'cost_price' => 43.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 8],
                ['name_ar' => 'مسطرة قياس 100 متر', 'name_en' => 'Measuring Tape 100m', 'sku' => 'MSC-MT100', 'price' => 120.00, 'cost_price' => 70.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 5],
                ['name_ar' => 'مسطرة قياس 150 متر', 'name_en' => 'Measuring Tape 150m', 'sku' => 'MSC-MT150', 'price' => 180.00, 'cost_price' => 104.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 3],
                ['name_ar' => 'مسطرة قياس 200 متر', 'name_en' => 'Measuring Tape 200m', 'sku' => 'MSC-MT200', 'price' => 240.00, 'cost_price' => 139.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 2],
                ['name_ar' => 'مسطرة قياس 300 متر', 'name_en' => 'Measuring Tape 300m', 'sku' => 'MSC-MT300', 'price' => 360.00, 'cost_price' => 209.00, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 1],
                ['name_ar' => 'مسطرة قياس 500 متر', 'name_en' => 'Measuring Tape 500m', 'sku' => 'MSC-MT500', 'price' => 600.00, 'cost_price' => 348.00, 'unit' => 'قطعة', 'brand' => 'Bosch', 'stock' => 1],
                ['name_ar' => 'سكين رسم متعدد الاستخدامات', 'name_en' => 'Multi-Purpose Utility Knife', 'sku' => 'MSC-UK1', 'price' => 6.00, 'cost_price' => 3.20, 'unit' => 'قطعة', 'brand' => 'Stanley', 'stock' => 100],
                ['name_ar' => 'شفرات سكين رسم - 10 قطع', 'name_en' => 'Utility Knife Blades - 10pc', 'sku' => 'MSC-UB10', 'price' => 3.00, 'cost_price' => 1.50, 'unit' => 'علبة', 'brand' => 'Stanley', 'stock' => 200],
                ['name_ar' => 'قلم تعليمPermanent', 'name_en' => 'Permanent Marker', 'sku' => 'MSC-PM1', 'price' => 2.00, 'cost_price' => 1.00, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 300],
                ['name_ar' => 'قلم تعليمأبيض', 'name_en' => 'White Marker', 'sku' => 'MSC-WM1', 'price' => 2.50, 'cost_price' => 1.20, 'unit' => 'قطعة', 'brand' => 'Generic', 'stock' => 250],
                ['name_ar' => 'شريط لاصق 48 مم - 50 متر', 'name_en' => 'Packing Tape 48mm - 50m', 'sku' => 'MSC-PT48', 'price' => 4.00, 'cost_price' => 2.00, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 200],
                ['name_ar' => 'شريط لاصق 24 مم - 50 متر', 'name_en' => 'Packing Tape 24mm - 50m', 'sku' => 'MSC-PT24', 'price' => 2.50, 'cost_price' => 1.20, 'unit' => 'لفة', 'brand' => 'Generic', 'stock' => 250],
                ['name_ar' => 'nails مسامير 25 مم - 100 قطعة', 'name_en' => 'Nails 25mm - 100pc', 'sku' => 'MSC-NL25', 'price' => 3.00, 'cost_price' => 1.50, 'unit' => 'علبة', 'brand' => 'Generic', 'stock' => 300],
                ['name_ar' => 'nails مسامير 40 مم - 100 قطعة', 'name_en' => 'Nails 40mm - 100pc', 'sku' => 'MSC-NL40', 'price' => 4.00, 'cost_price' => 2.00, 'unit' => 'علبة', 'brand' => 'Generic', 'stock' => 250],
                ['name_ar' => 'nails مسامير 50 مم - 100 قطعة', 'name_en' => 'Nails 50mm - 100pc', 'sku' => 'MSC-NL50', 'price' => 5.00, 'cost_price' => 2.80, 'unit' => 'علبة', 'brand' => 'Generic', 'stock' => 200],
                ['name_ar' => 'nails مسامير 75 مم - 100 قطعة', 'name_en' => 'Nails 75mm - 100pc', 'sku' => 'MSC-NL75', 'price' => 7.00, 'cost_price' => 3.80, 'unit' => 'علبة', 'brand' => 'Generic', 'stock' => 150],
                ['name_ar' => 'nails مسامير 100 مم - 100 قطعة', 'name_en' => 'Nails 100mm - 100pc', 'sku' => 'MSC-NL100', 'price' => 9.00, 'cost_price' => 5.00, 'unit' => 'علبة', 'brand' => 'Generic', 'stock' => 100],
                ['name_ar' => 'nails مسامير 125 مم - 100 قطعة', 'name_en' => 'Nails 125mm - 100pc', 'sku' => 'MSC-NL125', 'price' => 11.00, 'cost_price' => 6.00, 'unit' => 'علبة', 'brand' => 'Generic', 'stock' => 80],
            ],
        ],
    ];

    public function run(): void
    {
        $total = 0;

        foreach (self::DATA as $group) {
            $categoryId = $group['category_id'];
            $products = $group['products'];
            $created = 0;

            foreach ($products as $item) {
                $slug = Str::slug($item['name_en'], '-', 'en');

                // De-duplicate slugs
                $base = $slug;
                $n = 1;
                while (Product::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . (++$n);
                }

                Product::create([
                    'category_id' => $categoryId,
                    'name_ar' => $item['name_ar'],
                    'name_en' => $item['name_en'],
                    'slug' => $slug,
                    'sku' => $item['sku'],
                    'price' => $item['price'],
                    'cost_price' => $item['cost_price'],
                    'unit' => $item['unit'],
                    'brand' => $item['brand'],
                    'stock_quantity' => $item['stock'],
                    'in_stock' => $item['stock'] > 0,
                    'show_price' => true,
                    'is_active' => true,
                    'is_featured' => false,
                    'sort_order' => $created,
                ]);

                $created++;
            }

            $total += $created;
            $this->command?->info("Category {$group['category_slug']}: {$created} products created.");
        }

        $this->command?->info("Total products created: {$total}");
    }
}
