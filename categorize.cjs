const xlsx = require('xlsx');
const fs = require('fs');

const wb = xlsx.readFile('daily.xlsx');
const ws = wb.Sheets[wb.SheetNames[0]];
const raw = xlsx.utils.sheet_to_json(ws);

const rows = raw
    .map(r => ({
        name: (r['الصنف'] || '').trim(),
        unit: (r['الوحدة'] || '').trim(),
        qty: Number(r['الكمية']) || 0,
        cost: Number(r['التكلفة']) || 0,
        price: Number(r[' سعر المبيع مفرق ']) || 0,
    }))
    .filter(r => r.name.length > 0);

console.log('Total valid rows:', rows.length);

const rules = [
    { id: 15, name: 'مفصلات', kws: ['مفصلة', 'مفصلات'] },
    { id: 21, name: 'كراسي الحمام', kws: ['كرسي معلق', 'كرسي سنفور', 'كرسي بولو', 'جوان فاصل', 'جوان حشر', 'جوان مبروم', 'عدة كرسي', 'غطا كرسي', 'تحويلة كرسي'] },
    { id: 24, name: 'هرابات ومجالي', kws: ['هراب', 'مجلى', 'محلى'] },
    { id: 23, name: 'مصافي وريكارات', kws: ['مصفاية', 'مصافي', 'ريكار', 'ريغار', 'ريكارة'] },
    { id: 16, name: 'خلاطات وحنفيات', kws: ['خلاط', 'حنفية', 'مغسلة', 'شطاف', 'سحابة', 'طاسة دوش', 'سماعة دوش', 'كرت دوش', 'سيخ دوش', 'سخان', 'شك', 'دوش'] },
    { id: 18, name: 'اكسسوارات الحمام', kws: ['اكسسوار حمام', 'حمالة', 'بربيش', 'مسكة', 'شمسة', 'مرطب', 'فراشي طربوش', 'رقبة', 'وجاء', 'شمسة مسح', 'شمسة هرم', 'شمسة', 'برادي', 'دولاب', 'حماله'] },
    { id: 22, name: 'مواسير وواصل', kws: ['بوري', 'وصلة', 'براغي', 'كهربا', 'خرطوم', 'تية', 'تيه', 'سنة بعزقة', 'كوع 25', 'كوع بسن', 'تية 25', 'بوري 25', 'سدة طبة 25', 'بواري', 'عززقة'] },
    { id: 19, name: 'أوكار وصمامات', kws: ['اوكرة', 'اوكره', 'اوكر', 'كوع', 'صباب', 'سكر', 'قلب', 'فواشة', 'شراقة', 'سدة', 'نقاصة', 'نقاص', 'شد وصل', 'مربط', 'راكور', 'عزقة', 'طبة خلاط', 'بياض سكر', 'تطويلة سكر', 'قلب خلاط', 'قلب نحاس', 'كباسة', 'كرت شطاف', 'سدة طبة', 'برجور'] },
    { id: 17, name: 'عدد وأدوات', kws: ['مفتاح', 'مطرقة', 'حجر', 'مفك', 'مقص', 'بانسة', 'دسك', 'ديسك', 'فرد سليكون', 'طقم عزقة', 'حبسات', 'طقم مسدس', 'كاوي', 'خيط نايلون', 'متر', 'ميزان', 'ترنلك', 'حبل', 'مشرط', 'مطاطة', 'قفل درج', 'قفل منحس', 'زيرو', 'لزيو', 'غراء', 'معجون', 'كفوف', 'حفر', 'طبش', 'اسافين', 'تيكو', 'ورق حف', 'ورق لزق', 'سلاسل', 'داموتليك', 'طاسة فلين', 'دقر', 'زاوية', 'بوردة', 'طقم مسدس', 'حبسات', 'ترنلك', 'ميزان', 'خيط', 'مطاطة', 'قفل', 'فرد سليكون', 'طقم عزقة', 'ديسك', 'دسك', 'حجر قص', 'حجر جلخ', 'مفتاح رنش', 'مفتاح امولية', 'بانسة لقط', 'زند', 'فراشي', 'مسطرين', 'مالج', 'عدة'] },
    { id: 14, name: 'مواد سباكة وصحية', kws: ['بربيش', 'بصاب', 'تيفلون', 'جوان', 'بخاخ', 'اسافين', 'سكة خرادق', 'سليكون', 'سيخ', 'صباب', 'طبة', 'غطا', 'قلب', 'كوع', 'مربط', 'مسكة', 'نقاصة', 'هراب', 'وجاء', 'وصلة', 'سدة', 'تي', 'تية', 'تيه', 'بوري', 'راكور', 'شراقة', 'فواشة', 'شد وصل', 'راكور خزان', 'عزقة خلاط', 'طبة خلاط', 'بياض سكر', 'تطويلة سكر', 'قلب خلاط', 'قلب نحاس', 'كباسة', 'كرت شطاف', 'سدة طبة', 'جوان حشر', 'جوان مبروم', 'جوان فاصل'] },
    { id: 13, name: 'المواد الاستهلاكية والأدوات', kws: ['سليكون', 'تيفلون', 'ورق', 'غراء', 'معجون', 'خيط', 'لزيو', 'زيرو', 'كفوف', 'سلاسل', 'حبل', 'اسافين', 'سكة', 'بخاخ', 'طاسة', 'داموتليك', 'ابوكسي', 'تيكو'] },
];

function categorize(name) {
    const lower = name.toLowerCase();
    for (const rule of rules) {
        for (const kw of rule.kws) {
            if (lower.includes(kw.toLowerCase())) {
                return rule;
            }
        }
    }
    return null;
}

// Categorize — default uncategorized to category 13 (general consumables)
const results = rows.map(r => {
    const cat = categorize(r.name);
    const catId = cat ? cat.id : 13;
    return { ...r, category_id: catId, category_name: cat ? cat.name : 'المواد الاستهلاكية والأدوات' };
});

// Distribution
const dist = {};
let uncategorized = [];
results.forEach(r => {
    const key = r.category_name;
    dist[key] = (dist[key] || 0) + 1;
    if (r.category_id === 13 && !categorize(r.name)) uncategorized.push(r.name);
});

console.log('\n=== DISTRIBUTION ===');
Object.entries(dist).sort((a, b) => b[1] - a[1]).forEach(([k, v]) => console.log(`${v}\t${k}`));

console.log('\n=== DEFAULTED TO CAT 13 (' + uncategorized.length + ') ===');
uncategorized.forEach(n => console.log('  - ' + n));

fs.writeFileSync('products_import.json', JSON.stringify(results, null, 2));
console.log('\nWrote products_import.json with', results.length, 'products');
