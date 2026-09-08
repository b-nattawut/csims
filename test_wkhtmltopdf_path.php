<?php
/**
 * ทดสอบหา path ของ wkhtmltopdf
 */

echo "<h2>ทดสอบหา wkhtmltopdf path</h2>";

$possiblePaths = [
    '/c/Program Files/wkhtmltopdf/bin/wkhtmltopdf.exe',
    'C:/Program Files/wkhtmltopdf/bin/wkhtmltopdf.exe',
    'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
    '/usr/local/bin/wkhtmltopdf',
    '/usr/bin/wkhtmltopdf',
];

foreach ($possiblePaths as $path) {
    $exists = file_exists($path) ? '✅ เจอ!' : '❌ ไม่เจอ';
    echo "<p>$path - $exists</p>";
}

echo "<hr>";
echo "<p>PHP_OS: " . PHP_OS . "</p>";
echo "<p>DIRECTORY_SEPARATOR: " . DIRECTORY_SEPARATOR . "</p>";
