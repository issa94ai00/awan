<?php

$path = __DIR__ . '/../daily.xlsx';

$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    fwrite(STDERR, "Unable to open $path\n");
    exit(1);
}

echo "Entries:\n";
for ($i = 0; $i < $zip->numFiles; $i++) {
    echo $zip->getNameIndex($i) . "\n";
}

$entry = $zip->getFromName('xl/workbook.xml');
if ($entry !== false) {
    echo "\nWorkbook XML found\n";
}

$shared = $zip->getFromName('xl/sharedStrings.xml');
if ($shared !== false) {
    echo "\nShared strings first 10:\n";
    $xml = new SimpleXMLElement($shared);
    $count = 0;
    foreach ($xml->si as $si) {
        echo trim((string) $si->t) . "\n";
        $count++;
        if ($count >= 10) break;
    }
}

$sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
if ($sheet !== false) {
    echo "\nSheet1 rows first 20:\n";
    $xml = new SimpleXMLElement($sheet);
    $count = 0;
    foreach ($xml->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            $t = (string) $c['t'];
            $v = (string) $c->v;
            $cells[] = "$ref|$t|$v";
        }
        echo implode(',', $cells) . "\n";
        $count++;
        if ($count >= 20) break;
    }
}

$zip->close();
