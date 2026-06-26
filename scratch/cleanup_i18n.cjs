const fs = require('fs');
const path = require('path');

const i18nFile = path.join(__dirname, '../resources/js/i18n.js');

function cleanup() {
    let content = fs.readFileSync(i18nFile, 'utf8');
    
    // Regex to remove the EN Block
    content = content.replace(/\/\/ --- Admin Localization Keys ---[\s\S]*?(?=search_product: 'Search for a product...',)/g, '');
    
    // Regex to remove the AR Block
    content = content.replace(/\/\/ --- Admin Localization Keys ---[\s\S]*?(?=search_product: 'ابحث عن منتج...',)/g, '');
    
    fs.writeFileSync(i18nFile, content, 'utf8');
    console.log("Successfully restored resources/js/i18n.js to original clean state.");
}

cleanup();
