const fs = require('fs');
const path = require('path');

const adminDir = path.join(__dirname, '../resources/js/views/admin');
const outputJson = path.join(__dirname, 'all_arabic_strings.json');

const arabicRegex = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]+/;

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

const vueFiles = getVueFiles(adminDir);
console.log(`Found ${vueFiles.length} Vue files to scan.`);

const uniqueStrings = new Set();
const details = {};

function addString(str, file, context) {
    const trimmed = str.trim();
    if (!trimmed || !arabicRegex.test(trimmed)) return;
    
    let cleaned = trimmed;
    // Strip outer quotes if matched as literal
    if ((cleaned.startsWith("'") && cleaned.endsWith("'")) || 
        (cleaned.startsWith('"') && cleaned.endsWith('"')) ||
        (cleaned.startsWith('`') && cleaned.endsWith('`'))) {
        cleaned = cleaned.slice(1, -1);
    }
    
    const finalStr = cleaned.trim();
    if (finalStr && arabicRegex.test(finalStr)) {
        uniqueStrings.add(finalStr);
        if (!details[finalStr]) {
            details[finalStr] = [];
        }
        details[finalStr].push({ file: path.relative(path.join(__dirname, '..'), file), context });
    }
}

for (const file of vueFiles) {
    const content = fs.readFileSync(file, 'utf8');
    
    // Parse template block
    const templateMatch = content.match(/<template>([\s\S]*)<\/template>/i);
    if (templateMatch) {
        const template = templateMatch[1];
        
        // Split template into tags and text nodes
        const parts = template.split(/(<[^>]*>)/g);
        for (let i = 0; i < parts.length; i++) {
            const part = parts[i];
            if (!part) continue;
            
            if (part.startsWith('<') && part.endsWith('>')) {
                // It's a tag: match attribute values containing Arabic
                // E.g. placeholder="ابحث" or :label="'اسم'" or confirm-button-text="نعم"
                const attrRegex = /([\b:[a-zA-Z0-9-]+)\s*=\s*(['"])([\s\S]*?)\2/g;
                let attrMatch;
                while ((attrMatch = attrRegex.exec(part)) !== null) {
                    const attrVal = attrMatch[3];
                    if (arabicRegex.test(attrVal)) {
                        addString(attrVal, file, `attribute:${attrMatch[1]}`);
                    }
                }
            } else {
                // It's a text node: split by interpolations {{ ... }}
                const textParts = part.split(/(\{\{[\s\S]*?\}\})/g);
                for (let j = 0; j < textParts.length; j++) {
                    const textPart = textParts[j];
                    if (textPart.startsWith('{{') && textPart.endsWith('}}')) {
                        // Interpolation expression - match string literals inside it
                        const stringRegex = /(['"`])([\s\S]*?)\1/g;
                        let strMatch;
                        while ((strMatch = stringRegex.exec(textPart)) !== null) {
                            if (arabicRegex.test(strMatch[2])) {
                                addString(strMatch[2], file, 'template_interpolation_literal');
                            }
                        }
                    } else {
                        // Plain text: extract if it has Arabic characters
                        if (arabicRegex.test(textPart)) {
                            addString(textPart, file, 'tag_text');
                        }
                    }
                }
            }
        }
    }
    
    // Parse script block
    const scriptMatch = content.match(/<script[\s\S]*?>([\s\S]*)<\/script>/i);
    if (scriptMatch) {
        const script = scriptMatch[1];
        
        // Match JS string literals: single-quote, double-quote, and backticks (including dynamic backticks)
        // A simple scanner to find string literals in Javascript code
        let index = 0;
        while (index < script.length) {
            const char = script[index];
            if (char === "'" || char === '"' || char === '`') {
                const quoteType = char;
                let literal = '';
                index++; // skip opening quote
                while (index < script.length && script[index] !== quoteType) {
                    if (script[index] === '\\') {
                        literal += script[index] + (script[index + 1] || '');
                        index += 2;
                    } else {
                        literal += script[index];
                        index++;
                    }
                }
                if (arabicRegex.test(literal)) {
                    addString(quoteType + literal + quoteType, file, 'script_string');
                }
            }
            index++;
        }
    }
}

const list = Array.from(uniqueStrings);
fs.writeFileSync(outputJson, JSON.stringify({ count: list.length, strings: list, details }, null, 4));
console.log(`Scan completed. Found ${list.length} unique Arabic strings. Saved to scratch/all_arabic_strings.json`);
