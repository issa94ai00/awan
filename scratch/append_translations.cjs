const fs = require('fs');
const path = require('path');

const mappingJson = path.join(__dirname, 'all_translations.json');
const i18nFile = path.join(__dirname, '../resources/js/i18n.js');

const specialCases = {
    "delete_employee_confirm": {
        en: "Do you want to delete employee {name}?",
        ar: "هل تريد حذف الموظف {name}؟"
    },
    "bulk_disabled_products": {
        en: "Disabled {count} products",
        ar: "تم تعطيل {count} منتج"
    },
    "bulk_deleted_products": {
        en: "Deleted {count} products",
        ar: "تم حذف {count} منتج"
    },
    "image_size_limit": {
        en: "Image size must be less than {maxSize}MB",
        ar: "حجم الصورة يجب أن يكون أقل من {maxSize}MB"
    },
    "delete_category_confirm": {
        en: "Are you sure you want to delete category \"{name}\"?",
        ar: "هل أنت متأكد من حذف الفئة \"{name}\"؟"
    }
};

function cleanString(str) {
    let s = str;
    // Remove backslashes escaping quotes
    s = s.replace(/\\"/g, '"').replace(/\\'/g, "'");
    // If it's a template literal stub, convert ${...} to {...}
    s = s.replace(/\$\{(.*?)\}/g, (match, expr) => {
        return `{${expr.split('.').pop().replace(/[^a-zA-Z0-9]/g, '_')}}`;
    });
    return s;
}

function main() {
    const mapping = JSON.parse(fs.readFileSync(mappingJson, 'utf8'));
    let i18nContent = fs.readFileSync(i18nFile, 'utf8');
    
    // Group translations by translation key
    const enDict = {};
    const arDict = {};
    
    for (const [arStr, data] of Object.entries(mapping)) {
        // Clean Arabic string
        let arClean = arStr;
        if (data.isLiteral) {
            arClean = arStr.slice(1, -1);
        }
        arClean = cleanString(arClean);
        
        // Clean English string
        const enClean = cleanString(data.en);
        
        // Save
        enDict[data.key] = enClean;
        arDict[data.key] = arClean;
    }
    
    // Apply special cases (overwrite placeholders)
    for (const [key, data] of Object.entries(specialCases)) {
        enDict[key] = data.en;
        arDict[key] = data.ar;
    }
    
    // Format translations to string blocks
    let enBlock = "\n        // --- Admin Localization Keys ---\n";
    for (const [k, v] of Object.entries(enDict)) {
        const escapedVal = v.replace(/'/g, "\\'").replace(/\r?\n/g, "\\n");
        enBlock += `        '${k}': '${escapedVal}',\n`;
    }
    
    let arBlock = "\n        // --- Admin Localization Keys ---\n";
    for (const [k, v] of Object.entries(arDict)) {
        const escapedVal = v.replace(/'/g, "\\'").replace(/\r?\n/g, "\\n");
        arBlock += `        '${k}': '${escapedVal}',\n`;
    }
    
    // Insert EN Block in i18n.js
    // We locate search_product: 'Search for a product...',
    const enTarget = "search_product: 'Search for a product...',";
    if (i18nContent.includes(enTarget)) {
        i18nContent = i18nContent.replace(enTarget, enBlock + "        " + enTarget);
        console.log("Inserted English translation block.");
    } else {
        console.error("Could not find English target search_product in i18n.js");
    }
    
    // Insert AR Block in i18n.js
    const arTarget = "search_product: 'ابحث عن منتج...',";
    if (i18nContent.includes(arTarget)) {
        i18nContent = i18nContent.replace(arTarget, arBlock + "        " + arTarget);
        console.log("Inserted Arabic translation block.");
    } else {
        console.error("Could not find Arabic target search_product in i18n.js");
    }
    
    fs.writeFileSync(i18nFile, i18nContent, 'utf8');
    console.log("Successfully updated resources/js/i18n.js");
}

main();
