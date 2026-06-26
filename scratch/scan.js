const fs = require('fs');
const path = require('path');

const adminDir = path.join(__dirname, '../resources/js/views/admin');
const outputJson = path.join(__dirname, 'detected_arabic.json');

const arabicRegex = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]+/g;

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

// Helper to normalize and clean strings
function addString(str, file, context) {
    const trimmed = str.trim();
    if (!trimmed || !arabicRegex.test(trimmed)) return;
    
    // Clean outer quotes if any
    let cleaned = trimmed;
    if ((cleaned.startsWith("'") && cleaned.endsWith("'")) || (cleaned.startsWith('"') && cleaned.endsWith('"'))) {
        cleaned = cleaned.slice(1, -1);
    }
    
    if (cleaned.trim() && arabicRegex.test(cleaned)) {
        uniqueStrings.add(cleaned.trim());
        if (!details[cleaned.trim()]) {
            details[cleaned.trim()] = [];
        }
        details[cleaned.trim()].push({ file: path.relative(path.join(__dirname, '..'), file), context });
    }
}

for (const file of vueFiles) {
    const content = fs.readFileSync(file, 'utf8');
    
    // 1. Match template text content (between > and <, or surrounding interpolations)
    // We can do a simpler match of text segments inside <template>
    const templateMatch = content.match(/<template>([\s\S]*?)<\/template>/);
    if (templateMatch) {
        const template = templateMatch[1];
        
        // Find text nodes between tags
        const tagTextRegex = />([^<]*?[\u0600-\u06FF][^<]*?)</g;
        let match;
        while ((match = tagTextRegex.exec(template)) !== null) {
            // Split by curly braces if there is interpolation
            const parts = match[1].split(/\{\{[\s\S]*?\}\}/);
            for (const part of parts) {
                if (arabicRegex.test(part)) {
                    addString(part, file, 'tag_text');
                }
            }
        }
        
        // Find attribute values containing Arabic
        const attrRegex = /(\b[a-zA-Z0-9-:]+)\s*=\s*(['"])([^'"]*?[\u0600-\u06FF][^'"]*?)\2/g;
        while ((match = attrRegex.exec(template)) !== null) {
            addString(match[3], file, `attribute:${match[1]}`);
        }
    }
    
    // 2. Match script string literals (single, double, or backtick quotes)
    const scriptMatch = content.match(/<script[\s\S]*?>([\s\S]*?)<\/script>/);
    if (scriptMatch) {
        const script = scriptMatch[1];
        const stringRegex = /(['"`])([^'"`]*?[\u0600-\u06FF][^'"`]*?)\1/g;
        let match;
        while ((match = stringRegex.exec(script)) !== null) {
            addString(match[2], file, 'script_string');
        }
    }
}

const list = Array.from(uniqueStrings);
fs.writeFileSync(outputJson, JSON.stringify({ count: list.length, strings: list, details }, null, 4));
console.log(`Scan completed. Found ${list.length} unique Arabic strings. Saved to scratch/detected_arabic.json`);
