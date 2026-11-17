<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS));
$count = 0;
foreach ($files as $file) {
    if (!$file->isFile() || str_contains($file->getPathname(), '/vendor/') || $file->getExtension() !== 'php') continue;
    passthru(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getPathname()).' > /dev/null', $status);
    if ($status !== 0) exit($status);
    $count++;
}
echo "PASS PHP ".PHP_VERSION." lint: $count files\n";
