<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$count = 0;
foreach (['app', 'bootstrap', 'config', 'public', 'scripts', 'tests'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname());
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            fwrite(STDERR, 'Sintaxis inválida en ' . $file->getPathname() . "\n");
            exit(1);
        }
        $count++;
    }
}
printf("Sintaxis PHP: %d archivos OK\n", $count);
