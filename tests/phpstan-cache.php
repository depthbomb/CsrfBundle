<?php

declare(strict_types=1);

return [
    'parameters' => [
        'tmpDir' => sys_get_temp_dir().'/csrf-bundle-phpstan-'.hash('sha256', getcwd()),
    ],
];
