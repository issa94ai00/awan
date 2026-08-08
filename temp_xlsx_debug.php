<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function dump($label, $value) {
    echo "$label: ";
    var_export($value);
    echo "\n";
}

$path = 'daily.xlsx';
if (!file_exists($path)) {
    echo "MISSING daily.xlsx\n";
    exit(1);
}
$zip = new ZipArchive();
$open = $zip->open($path);
dump('zip open', $open);
if ($open !== true) {
    echo "zip open failed\n";
    exit(1);
}

dump('numFiles', $zip->numFiles);
for ($i = 0; $i < $zip->numFiles; $i++) {
    echo "file[$i]=" . $zip->getNameIndex($i) . "\n";
}

$xmlStr = $zip->getFromName('xl/worksheets/sheet1.xml');
dump('sheet1.xml present', $xmlStr !== false);
$sharedStr = $zip->getFromName('xl/sharedStrings.xml');
dump('sharedStrings.xml present', $sharedStr !== false);
if ($xmlStr === false || $sharedStr === false) {
    $zip->close();
    exit(1);
}

dump('sheet1 length', strlen($xmlStr));
dump('sharedStrings length', strlen($sharedStr));

$ss = new SimpleXMLElement($sharedStr);
dump('sharedStrings count', count($ss->si));
foreach ($ss->si as $idx => $si) {
    if ($idx > 4) break;
    $t = isset($si->t) ? (string)$si->t : '';
    echo "SI[$idx]=" . $t . "\n";
}

$sheet = new SimpleXMLElement($xmlStr);
dump('sheet rows count', count($sheet->sheetData->row));
foreach ($sheet->sheetData->row as $idx => $row) {
    if ($idx > 10) break;
    echo "ROW $idx:\n";
    foreach ($row->c as $cell) {
        $ref = (string)$cell['r'];
        $t = (string)$cell['t'];
        $v = isset($cell->v) ? (string)$cell->v : '';
        if ($t === 's') {
            $val = isset($ss->si[intval($v)]->t) ? (string)$ss->si[intval($v)]->t : $v;
        } else {
            $val = $v;
        }
        echo "  $ref=$val\n";
    }
    echo "\n";
}

$zip->close();
