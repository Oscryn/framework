<?php

declare(strict_types=1);

putenv('DB_DRIVER=sqlite');
putenv('DB_NAME=:memory:');
putenv('APP_ENV=testing');

require __DIR__.'/../vendor/autoload.php';
