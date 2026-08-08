<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$path = 'daily.xlsx';
$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    echo "open failed\n";
    exit(1);
}
$xmlStr = $zip->getFromName('xl/worksheets/sheet1.xml');
$sharedStr = $zip->getFromName('xl/sharedStrings.xml');
$zip->close();

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
$rows = $sheet->sheetData->row;
foreach ($rows as $idx => $row) {
    if ($idx > 10) {
        break;
    }
    $cells = [];
    foreach ($row->c as $cell) {
        $ref = (string) $cell['r'];
        $type = (string) $cell['t'];
        $val = isset($cell->v) ? (string) $cell->v : '';
        if ($type === 's') {
            $lookup = intval($val);
            $val = isset($strings[$lookup]) ? $strings[$lookup] : $val;
        }
        $cells[$ref] = $val;
    }
    echo "ROW $idx\n";
    foreach ($cells as $ref => $value) {
        echo "  $ref = $value\n";
    }
    echo "\n";
}
