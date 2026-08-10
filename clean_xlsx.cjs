const xlsx = require('xlsx');
const fs = require('fs');

const wb = xlsx.readFile('daily.xlsx');
const ws = wb.Sheets[wb.SheetNames[0]];
const raw = xlsx.utils.sheet_to_json(ws);

function parseMoney(v) {
    return parseFloat(String(v).replace(/[^0-9.]/g, '')) || 0;
}

const rows = raw.map(r => ({
    name: (r['اسم المنتج'] || '').trim(),
    unit: (r['الوحدة'] || '').trim(),
    qty: Number(r['الكمية']) || 0,
    cost: parseMoney(r['التكلفة']),
    price: parseMoney(r['سعر المبيع مفرق']),
})).filter(r => r.name.length > 0);

console.log('Clean rows:', rows.length);
fs.writeFileSync('products_clean.json', JSON.stringify(rows, null, 2));
console.log('Wrote products_clean.json');
console.log('Sample:', JSON.stringify(rows[0]));
