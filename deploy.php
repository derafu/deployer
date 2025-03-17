<?php

declare(strict_types=1);

/**
 * Derafu: Deployer - PHP Deployer for multiple sites.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

use function Deployer\host;
use function Deployer\set;

// Get the current user and home directory.
$userInfo = posix_getpwuid(posix_geteuid());
echo "User: {$userInfo['name']} | Home: {$userInfo['dir']}" . PHP_EOL;

// Load the Deployer classes and the recipe.
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/recipe/multisites.php';

// Load the sites and normalize them.
$sites = require __DIR__ . '/sites.php';
foreach ($sites as $site => &$config) {
    if (is_string($config)) {
        $config = [
            'repository' => $config,
        ];
    }
}

// Set the sites.
set('sites', $sites);

// Set the www root path.
set('www_root_path', '/var/www/sites');

// Set the host for the development stage.
host('localhost')
    ->setRemoteUser('admin')
    ->setPort(2222)
    ->setLabels(['stage' => 'dev'])
    ->setSshArguments([
        '-o StrictHostKeyChecking=no',
        '-o UserKnownHostsFile=/dev/null',
    ]);
;

// Set the host for the production stage.
if (getenv('DEPLOYER_HOST')) {
    host(getenv('DEPLOYER_HOST'))
        ->setRemoteUser(getenv('DEPLOYER_USER') ?: 'admin')
        ->setPort(getenv('DEPLOYER_PORT') ?: 2222)
        ->setLabels(['stage' => 'prod'])
        ->setSshArguments([
            '-o StrictHostKeyChecking=no',
        ]);
    ;
    set('default_selector', 'stage=prod');
}
