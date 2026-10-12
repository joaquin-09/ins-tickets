<?php

declare(strict_types=1);

use InsTickets\Core\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('UTC');
Config::load(dirname(__DIR__) . '/.env');

return Config::class;
