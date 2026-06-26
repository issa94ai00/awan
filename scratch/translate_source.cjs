const fs = require('fs');
const path = require('path');

const adminDir = path.join(__dirname, '../resources/js/views/admin');
const mappingJson = path.join(__dirname, 'all_translations.json');

const specialCases = {
    // 1. Delete employee confirmation literal
    "`هل تود حذف الموظف ${employee.name}؟`": {
        key: "delete_employee_confirm",
        en: "Do you want to delete employee {name}?",
        ar: "هل تريد حذف الموظف {name}؟",
        replaceScript: "window.t('delete_employee_confirm', { name: employee.name })"
    },
    "'هل تود حذف الموظف ${employee.name}؟'": {
        key: "delete_employee_confirm",
        en: "Do you want to delete employee {name}?",
        ar: "هل تريد حذف الموظف {name}؟",
        replaceScript: "window.t('delete_employee_confirm', { name: employee.name })"
    },
    "\"هل تود حذف الموظف ${employee.name}؟\"": {
        key: "delete_employee_confirm",
        en: "Do you want to delete employee {name}?",
        ar: "هل تريد حذف الموظف {name}؟",
        replaceScript: "window.t('delete_employee_confirm', { name: employee.name })"
    },
    
    // 2. Bulk disabled products
    "`تم تعطيل ${result.succeeded.length} منتج`": {
        key: "bulk_disabled_products",
        en: "Disabled {count} products",
        ar: "تم تعطيل {count} منتج",
        replaceScript: "window.t('bulk_disabled_products', { count: result.succeeded.length })"
    },
    
    // 3. Bulk deleted products
    "`تم حذف ${result.succeeded} منتج`": {
        key: "bulk_deleted_products",
        en: "Deleted {count} products",
        ar: "تم حذف {count} منتج",
        replaceScript: "window.t('bulk_deleted_products', { count: result.succeeded })"
    },
    
    // 4. Image size limit
    "`حجم الصورة يجب أن يكون أقل من ${maxSizeMB}MB`": {
        key: "image_size_limit",
        en: "Image size must be less than {maxSize}MB",
        ar: "حجم الصورة يجب أن يكون أقل من {maxSize}MB",
        replaceScript: "window.t('image_size_limit', { maxSize: maxSizeMB })"
    },
    
    // 5. Delete category confirm
    "`هل أنت متأكد من حذف الفئة \"${category.name_ar}\"؟`": {
        key: "delete_category_confirm",
        en: "Are you sure you want to delete category \"{name}\"?",
        ar: "هل أنت متأكد من حذف الفئة \"{name}\"؟",
        replaceScript: "window.t('delete_category_confirm', { name: category.name_ar })"
    },
    "\"هل أنت متأكد من حذف الفئة \\\"${category.name_ar}\\\"؟\"": {
        key: "delete_category_confirm",
        en: "Are you sure you want to delete category \"{name}\"?",
        ar: "هل أنت متأكد من حذف الفئة \"{name}\"؟",
        replaceScript: "window.t('delete_category_confirm', { name: category.name_ar })"
    }
};

function getVueFiles(dir, files = []) {
    const list = fs.readdirSync(dir);
    for (const file of list) {
        const filePath = path.join(dir, file);
        const stat = fs.statSync(filePath);
        if (stat.isDirectory()) {
            getVueFiles(filePath, files);
        } else if (filePath.endsWith('.vue')) {
            files.push(filePath);
        }
    }
    return files;
}

function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function main() {
    const mapping = JSON.parse(fs.readFileSync(mappingJson, 'utf8'));
    const vueFiles = getVueFiles(adminDir);
    console.log(`Loaded mapping and found ${vueFiles.length} Vue files.`);
    
    // Sort strings by length in descending order to avoid partial replacement issues
    const sortedArabicStrings = Object.keys(mapping).sort((a, b) => b.length - a.length);
    
    let processedCount = 0;
    
    for (const file of vueFiles) {
        let content = fs.readFileSync(file, 'utf8');
        let fileModified = false;
        
        // 1. Handle special dynamic literals first (as script translations)
        for (const [literal, data] of Object.entries(specialCases)) {
            if (content.includes(literal)) {
                content = content.replaceAll(literal, data.replaceScript);
                fileModified = true;
            }
        }
        
        // 2. Separate into Template, Script, Style
        // We find the tag bounds
        const templateMatch = content.match(/<template>([\s\S]*)<\/template>/i);
        const scriptMatch = content.match(/<script([\s\S]*?)>([\s\S]*)<\/script>/i);
        
        if (templateMatch) {
            let templateBlock = templateMatch[1];
            let templateModified = false;
            
            // Split template into tags and text nodes
            const parts = templateBlock.split(/(<[^>]*>)/g);
            for (let i = 0; i < parts.length; i++) {
                let part = parts[i];
                if (!part) continue;
                
                if (part.startsWith('<') && part.endsWith('>')) {
                    // Tag node: replace attributes containing Arabic
                    for (const arStr of sortedArabicStrings) {
                        const data = mapping[arStr];
                        if (data.isLiteral) continue; // skip script literal matches here
                        
                        // Replace unprefixed attributes like label="العربية" -> :label="$t('key')"
                        // Match format: attr="arStr" or attr='arStr'
                        const attrRegex = new RegExp(`\\b(placeholder|label|title|confirm-button-text|cancel-button-text|active-text|inactive-text|content-position)\\s*=\\s*(['"])${escapeRegExp(arStr)}\\2`, 'g');
                        if (attrRegex.test(part)) {
                            part = part.replace(attrRegex, `:$1="$t('${data.key}')"`);
                            templateModified = true;
                        }
                        
                        // Replace prefixed attributes like :label="'العربية'" -> :label="$t('key')"
                        const prefixAttrRegex = new RegExp(`\\b(:[a-zA-Z0-9-]+)\\s*=\\s*(['"])(.*?)\\2`, 'g');
                        if (prefixAttrRegex.test(part)) {
                            part = part.replace(prefixAttrRegex, (match, attrName, quote, expr) => {
                                // check if expr contains single/double quoted arStr
                                const literalRegex = new RegExp(`(['"])${escapeRegExp(arStr)}\\1`, 'g');
                                if (literalRegex.test(expr)) {
                                    const newExpr = expr.replace(literalRegex, `$t('${data.key}')`);
                                    return `${attrName}=${quote}${newExpr}${quote}`;
                                }
                                return match;
                            });
                            templateModified = true;
                        }
                    }
                    parts[i] = part;
                } else {
                    // Text node: split by Vue interpolations {{ ... }}
                    const textParts = part.split(/(\{\{[\s\S]*?\}\})/g);
                    let textNodeModified = false;
                    
                    for (let j = 0; j < textParts.length; j++) {
                        let textPart = textParts[j];
                        if (!textPart) continue;
                        
                        if (textPart.startsWith('{{') && textPart.endsWith('}}')) {
                            // Interpolation: replace string literals containing Arabic
                            for (const arStr of sortedArabicStrings) {
                                const data = mapping[arStr];
                                const literalRegex = new RegExp(`(['"\`])${escapeRegExp(arStr)}\\1`, 'g');
                                if (literalRegex.test(textPart)) {
                                    textPart = textPart.replace(literalRegex, `$t('${data.key}')`);
                                    textNodeModified = true;
                                }
                            }
                        } else {
                            // Plain text node: replace exact Arabic occurrences with {{ $t('key') }}
                            for (const arStr of sortedArabicStrings) {
                                const data = mapping[arStr];
                                if (data.isLiteral) continue;
                                
                                const arRegex = new RegExp(escapeRegExp(arStr), 'g');
                                if (arRegex.test(textPart)) {
                                    textPart = textPart.replace(arRegex, `{{ $t('${data.key}') }}`);
                                    textNodeModified = true;
                                }
                            }
                        }
                        textParts[j] = textPart;
                    }
                    
                    if (textNodeModified) {
                        parts[i] = textParts.join('');
                        templateModified = true;
                    }
                }
            }
            
            if (templateModified) {
                const newTemplateBlock = parts.join('');
                content = content.replace(templateBlock, newTemplateBlock);
                fileModified = true;
            }
        }
        
        if (scriptMatch) {
            let scriptBlock = scriptMatch[2];
            const scriptHeader = scriptMatch[1];
            let scriptModified = false;
            
            // Replace literal occurrences in JS code block
            for (const arStr of sortedArabicStrings) {
                const data = mapping[arStr];
                // arStr is already formatted like 'العربية' or "العربية" or `العربية` in mapping if isLiteral is true
                if (data.isLiteral) {
                    const literalRegex = new RegExp(`${escapeRegExp(arStr)}\\s*(?!:)`, 'g');
                    if (literalRegex.test(scriptBlock)) {
                        scriptBlock = scriptBlock.replace(literalRegex, `window.t('${data.key}')`);
                        scriptModified = true;
                    }
                } else {
                    // Match any literal 'arStr', "arStr", `arStr` if not followed by a colon
                    const scriptLiteralRegex = new RegExp(`(['"\`])${escapeRegExp(arStr)}\\1\\s*(?!:)`, 'g');
                    if (scriptLiteralRegex.test(scriptBlock)) {
                        scriptBlock = scriptBlock.replace(scriptLiteralRegex, `window.t('${data.key}')`);
                        scriptModified = true;
                    }
                }
            }
            
            if (scriptModified) {
                // Keep the exact header intact
                content = content.replace(scriptMatch[0], `<script${scriptHeader}>${scriptBlock}</script>`);
                fileModified = true;
            }
        }
        
        if (fileModified) {
            fs.writeFileSync(file, content, 'utf8');
            processedCount++;
        }
    }
    
    console.log(`Finished processing all Vue files. Modified ${processedCount}/${vueFiles.length} files.`);
}

main();
