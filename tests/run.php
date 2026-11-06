<?php

declare(strict_types=1);

use PHPUnit\TextUI\Application;

require getcwd().'/vendor/autoload.php';

$cacheDirectory = sys_get_temp_dir().'/csrf-bundle-phpunit-'.hash('sha256', getcwd());

exit((new Application())->run([
    $argv[0],
    '--cache-directory',
    $cacheDirectory,
    ...array_slice($argv, 1),
]));
