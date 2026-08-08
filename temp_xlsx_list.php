<?php
$zip = new ZipArchive();
if ($zip->open('daily.xlsx') !== true) {
    echo "open failed\n";
    exit(1);
}
for ($i = 0; $i < $zip->numFiles; $i++) {
    echo $zip->getNameIndex($i) . "\n";
}
$zip->close();
