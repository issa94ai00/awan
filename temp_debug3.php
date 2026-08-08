<?php
$zip = new ZipArchive();
if ($zip->open("daily.xlsx") === true) {
    $xml = $zip->getFromName("xl/worksheets/sheet1.xml");
    $ssxml = $zip->getFromName("xl/sharedStrings.xml");
    $zip->close();
    $ss = new SimpleXMLElement($ssxml);
    $sheet = new SimpleXMLElement($xml);
    echo "rows=" . count($sheet->sheetData->row) . "\n";
    foreach ($sheet->sheetData->row as $idx => $row) {
        if ($idx > 10) break;
        echo "ROW $idx\n";
        foreach ($row->c as $cell) {
            $ref = (string) $cell['r'];
            $t = (string) $cell['t'];
            $v = isset($cell->v) ? (string) $cell->v : '';
            if ($t === 's') {
                $v = isset($ss->si[intval($v)]->t) ? (string) $ss->si[intval($v)]->t : $v;
            }
            echo "  $ref=$v\n";
        }
        echo "\n";
    }
} else {
    echo "fail\n";
}
