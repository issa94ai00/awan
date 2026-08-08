<?php
$path = __DIR__ . '/../daily.xlsx';
$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    fwrite(STDERR, "Unable to open $path\n");
    exit(1);
}

function getSharedStrings(ZipArchive $zip): array {
    $entry = $zip->getFromName('xl/sharedStrings.xml');
    if ($entry === false) {
        return [];
    }
    $xml = new SimpleXMLElement($entry);
    $values = [];
    foreach ($xml->si as $si) {
        $text = '';
        if (isset($si->t)) {
            $text = (string) $si->t;
        } else {
            foreach ($si->r as $run) {
                $text .= (string) $run->t;
            }
        }
        $values[] = $text;
    }
    return $values;
}

function colIndex(string $ref): int {
    preg_match('/^([A-Z]+)(\d+)$/', $ref, $matches);
    $col = $matches[1] ?? '';
    $index = 0;
    for ($i = 0, $len = strlen($col); $i < $len; $i++) {
        $index = $index * 26 + (ord($col[$i]) - 64);
    }
    return $index - 1;
}

$shared = getSharedStrings($zip);
$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
if ($sheetXml === false) {
    fwrite(STDERR, "Missing sheet1.xml\n");
    exit(1);
}
$xml = new SimpleXMLElement($sheetXml);
$rows = [];
foreach ($xml->sheetData->row as $row) {
    $cells = [];
    foreach ($row->c as $c) {
        $ref = (string) $c['r'];
        $type = (string) $c['t'];
        $value = '';
        if (isset($c->v)) {
            $value = (string) $c->v;
            if ($type === 's') {
                $idx = intval($value);
                $value = $shared[$idx] ?? $value;
            }
        }
        $cells[colIndex($ref)] = $value;
    }
    ksort($cells);
    $rows[] = array_values($cells);
}

foreach ($rows as $ri => $row) {
    echo ($ri+1) . ': ' . json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    if ($ri >= 50) break;
}
$zip->close();
