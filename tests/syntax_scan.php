<?php
/**
 * Full PHP syntax scanner for the skite/app directory.
 */

$errors = 0;
$checked = 0;
$dirs = [
    'c:/xampp/htdocs/skite/app',
    'c:/xampp/htdocs/skite/tests',
    'c:/xampp/htdocs/skite/config',
];

foreach ($dirs as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $checked++;
        $output = shell_exec('C:/xampp/php/php.exe -l "' . $file->getRealPath() . '" 2>&1');
        if (strpos($output, 'No syntax errors') === false) {
            echo 'SYNTAX ERROR: ' . $file->getRealPath() . PHP_EOL;
            echo trim($output) . PHP_EOL . PHP_EOL;
            $errors++;
        }
    }
}

echo PHP_EOL;
echo "Checked: {$checked} files" . PHP_EOL;
echo ($errors === 0 ? '[PASS] All files syntax OK' : "[FAIL] {$errors} file(s) with syntax errors") . PHP_EOL;
exit($errors > 0 ? 1 : 0);
