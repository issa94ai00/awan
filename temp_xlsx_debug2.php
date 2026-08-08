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

echo "sheet1 length=" . strlen($xmlStr) . "\n";
echo "sharedStrings length=" . strlen($sharedStr) . "\n";
echo "sheet1 head:\n" . substr($xmlStr, 0, 1200) . "\n---\n";
echo "sharedStrings head:\n" . substr($sharedStr, 0, 600) . "\n---\n";

$sheet = new SimpleXMLElement($xmlStr);
$row = $sheet->sheetData->row[0];
if (!$row) {
    echo "no first row\n";
    exit(1);
}

echo "first row children count=" . count($row->children()) . "\n";
foreach ($row->children() as $child) {
    echo "child: " . $child->getName() . "\n";
}
