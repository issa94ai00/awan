const fs = require('fs');
const path = require('path');

const file = path.join(__dirname, '../resources/js/views/admin/categories/Index.vue');
const content = fs.readFileSync(file, 'utf8');

const templateMatch = content.match(/<template>([\s\S]*)<\/template>/i);
if (!templateMatch) {
    console.log("No template match!");
    process.exit(1);
}

const template = templateMatch[1];
console.log("Template length:", template.length);

const parts = template.split(/(<[^>]*>)/g);
console.log("Number of parts:", parts.length);

const arabicRegex = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]+/g;

for (let i = 0; i < parts.length; i++) {
    const part = parts[i];
    if (!part) continue;
    
    if (part.startsWith('<') && part.endsWith('>')) {
        console.log(`Tag [${i}]:`, part);
    } else {
        if (arabicRegex.test(part)) {
            console.log(`Text [${i}] (contains Arabic):`, JSON.stringify(part));
        }
    }
}
