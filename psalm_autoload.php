<?php

declare(strict_types=1);

require __DIR__ . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';

// Pull in any helpers the package itself ships, so Psalm sees their function
// definitions when analysing src/.
$helperDir = __DIR__ . '/src/Helpers';
if (is_dir($helperDir)) {
    foreach (glob($helperDir . '/*_helper.php') ?: [] as $file) {
        require_once $file;
    }
}
