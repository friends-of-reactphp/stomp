<?php

if (!isset($argv[1]) || !file_exists(__DIR__.'/'.$argv[1].'.php')) {
    $current = isset($argv[1]) ? $argv[1] : null;
    $configs = array();

    foreach (new FilesystemIterator(__DIR__) as $fileInfo) {
        if (basename(__FILE__) === $fileInfo->getFilename()) {
            continue;
        }

        $configs[] = $fileInfo->getBasename('.php');
    }

    echo "Usage: php $argv[0] CONFIG\n\n";
    echo sprintf("Available configs: %s\n", implode(', ', $configs));
    exit(1);
}

return require __DIR__.'/'.$argv[1].'.php';
