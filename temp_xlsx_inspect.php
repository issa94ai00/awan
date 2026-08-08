<?php
$zip = new ZipArchive();
if ($zip->open('daily.xlsx') !== true) {
    echo "open failed\n";
    exit(1);
}
$xmlStr = $zip->getFromName('xl/worksheets/sheet1.xml');
$sharedStr = $zip->getFromName('xl/sharedStrings.xml');
$ss = new SimpleXMLElement($sharedStr);
$strings = [];
foreach ($ss->si as $si) {
    $text = '';
    if (isset($si->t)) {
        $text = (string) $si->t;
    } else {
        foreach ($si->r as $r) {
            $text .= (string) $r->t;
        }
    }
    $strings[] = $text;
}
$sheet = new SimpleXMLElement($xmlStr);
foreach ($sheet->sheetData->row as $idx => $row) {
    if ($idx > 5) break;
    foreach ($row->c as $cell) {
        $ref = (string) $cell['r'];
        $type = (string) $cell['t'];
        $val = isset($cell->v) ? (string) $cell->v : '';
        if ($type === 's') {
            $val = $strings[intval($val)] ?? $val;
        }
        echo "$ref=$val\t";
    }
    echo "\n";
}
$zip->close();
