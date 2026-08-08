<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$path = __DIR__ . DIRECTORY_SEPARATOR . 'daily.xlsx';
if (!file_exists($path)) {
    echo "MISSING daily.xlsx\n";
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    echo "CANNOT OPEN ZIP\n";
    exit(1);
}

$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
$sharedXml = $zip->getFromName('xl/sharedStrings.xml');
$zip->close();

$sharedStrings = [];
if ($sharedXml !== false) {
    $shared = new SimpleXMLElement($sharedXml);
    foreach ($shared->si as $si) {
        if (isset($si->t)) {
            $sharedStrings[] = (string) $si->t;
        } else {
            $text = '';
            foreach ($si->r as $r) {
                $text .= (string) $r->t;
            }
            $sharedStrings[] = $text;
        }
    }
}

$sheet = new SimpleXMLElement($sheetXml);
$rows = [];
foreach ($sheet->sheetData->row as $row) {
    $cells = [];
    foreach ($row->c as $cell) {
        $ref = (string) $cell['r'];
        $col = preg_replace('/\d+$/', '', $ref);
        $idx = 0;
        foreach (str_split($col) as $letter) {
            $idx = $idx * 26 + (ord($letter) - 64);
        }
        $idx--;
        $value = '';
        if (isset($cell->v)) {
            $value = (string) $cell->v;
            if ((string) $cell['t'] === 's' && is_numeric($value)) {
                $value = $sharedStrings[intval($value)] ?? $value;
            }
        }
        $cells[$idx] = $value;
    }
    ksort($cells);
    $rows[] = $cells;
}

echo "ROWS=" . count($rows) . "\n";
for ($i = 0; $i < min(12, count($rows)); $i++) {
    $row = $rows[$i];
    echo "ROW $i:";
    foreach ($row as $col => $value) {
        echo " [$col]=" . trim($value);
    }
    echo "\n";
}

// Print likely header row values
for ($i = 0; $i < min(12, count($rows)); $i++) {
    $values = array_values($rows[$i]);
    $print = [];
    foreach ($values as $value) {
        $print[] = preg_replace('/\s+/', ' ', trim($value));
    }
    echo "HR $i: " . implode(' | ', $print) . "\n";
}
