const fs = require('fs');
const path = require('path');

const inputJson = path.join(__dirname, 'all_arabic_strings.json');
const outputJson = path.join(__dirname, 'all_translations.json');

async function translateText(text) {
    try {
        // Simple escape for URL
        const url = `https://translate.googleapis.com/translate_a/single?client=gtx&sl=ar&tl=en&dt=t&q=${encodeURIComponent(text)}`;
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        // Google Translate structure: [[[translation, original, ...], ...]]
        if (data && data[0] && data[0][0] && data[0][0][0]) {
            return data[0].map(item => item[0]).join('').trim();
        }
        return text;
    } catch (error) {
        console.error(`Failed to translate: "${text}"`, error);
        return text;
    }
}

// Generate a clean snake_case key from English text
function generateKey(enText) {
    // Check if it's too long, truncate if necessary
    let clean = enText
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
    
    // If empty or just numbers, fallback
    if (!clean || /^[0-9_]+$/.test(clean)) {
        clean = 'key_' + Math.random().toString(36).substr(2, 5);
    }
    
    // Limit length to 40 chars for neat keys
    if (clean.length > 40) {
        clean = clean.split('_').slice(0, 5).join('_');
    }
    return clean;
}

// Map of manual overrides to guarantee perfect key names and translations for standard words
const overrideMap = {
    "هذا الحقل مطلوب": { key: "required_field", en: "This field is required" },
    "الاسم": { key: "name", en: "Name" },
    "اسم": { key: "name", en: "Name" },
    "حفظ": { key: "save", en: "Save" },
    "إلغاء": { key: "cancel", en: "Cancel" },
    "نشط": { key: "active", en: "Active" },
    "غير نشط": { key: "inactive", en: "Inactive" },
    "تعديل": { key: "edit", en: "Edit" },
    "حذف": { key: "delete", en: "Delete" },
    "الحالة": { key: "status", en: "Status" },
    "البريد الإلكتروني": { key: "email", en: "Email" },
    "رقم الهاتف": { key: "phone", en: "Phone" },
    "العنوان": { key: "address", en: "Address" },
    "القسم": { key: "department", en: "Department" },
    "الوظيفة": { key: "position", en: "Position" },
    "تحديث": { key: "update", en: "Update" },
    "إضافة": { key: "add", en: "Add" },
    "نعم": { key: "yes", en: "Yes" },
    "لا": { key: "no", en: "No" },
    "الكل": { key: "all", en: "All" },
    "أوان التقدم": { key: "site_name", en: "Awan Altakaddom" },
    "لوحة التحكم": { key: "dashboard", en: "Dashboard" }
};

async function main() {
    const rawData = JSON.parse(fs.readFileSync(inputJson, 'utf8'));
    const strings = rawData.strings;
    console.log(`Loaded ${strings.length} strings to translate.`);
    
    const mapping = {};
    if (fs.existsSync(outputJson)) {
        try {
            const existingMapping = JSON.parse(fs.readFileSync(outputJson, 'utf8'));
            Object.assign(mapping, existingMapping);
            console.log(`Loaded ${Object.keys(existingMapping).length} existing translations from cache.`);
        } catch (e) {
            console.warn("Failed to load existing cache:", e);
        }
    }
    let count = 0;
    
    for (const str of strings) {
        count++;
        if (count % 30 === 0) {
            console.log(`Processed ${count}/${strings.length}...`);
        }
        
        // Skip empty strings
        if (!str.trim()) continue;

        // Skip if already in cache
        if (mapping[str]) {
            continue;
        }
        
        // Check manual override
        if (overrideMap[str]) {
            mapping[str] = overrideMap[str];
            continue;
        }
        
        // Handle template string literals containing variable interpolations ${...}
        // or Vue double curly braces {{...}}
        let textToTranslate = str;
        const variables = [];
        
        // Replace ${expression} with {varIndex} placeholder
        textToTranslate = textToTranslate.replace(/\$\{(.*?)\}/g, (match, expression) => {
            const varPlaceholder = `{var${variables.length}}`;
            variables.push({ placeholder: varPlaceholder, original: match });
            return varPlaceholder;
        });
        
        // Clean outer quotes from the string if matched as a script literal
        let isLiteral = false;
        let quoteChar = '';
        if ((textToTranslate.startsWith("'") && textToTranslate.endsWith("'")) || 
            (textToTranslate.startsWith('"') && textToTranslate.endsWith('"')) ||
            (textToTranslate.startsWith('`') && textToTranslate.endsWith('`'))) {
            quoteChar = textToTranslate[0];
            textToTranslate = textToTranslate.slice(1, -1);
            isLiteral = true;
        }
        
        // Call translation API
        let enTranslation = await translateText(textToTranslate);
        
        // Restore variable placeholders
        for (const v of variables) {
            enTranslation = enTranslation.replace(v.placeholder, v.original);
        }
        
        // Generate key from English translation
        // Clean out any template literal expressions from the key generation
        let keyBase = enTranslation.replace(/\$\{(.*?)\}/g, 'var').replace(/[{}]/g, '');
        let key = generateKey(keyBase);
        
        // Add back quotes if it was a literal, but keep clean versions for translation dictionary
        mapping[str] = {
            key: key,
            en: enTranslation,
            isLiteral: isLiteral,
            quoteChar: quoteChar
        };
        
        // Delay slightly to prevent API rate limiting
        await new Promise(resolve => setTimeout(resolve, 50));
    }
    
    fs.writeFileSync(outputJson, JSON.stringify(mapping, null, 4));
    console.log(`Successfully translated all strings and saved mapping to scratch/all_translations.json`);
}

main().catch(err => {
    console.error("Error in main translation script:", err);
});
