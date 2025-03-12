<?php

declare(strict_types=1);

/**
 * Derafu: Deployer - PHP Deployer for multiple sites
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

require __DIR__ . '/../vendor/deployer/deployer/recipe/common.php';

use Symfony\Component\Console\Input\InputOption;

use function Deployer\desc;
use function Deployer\input;
use function Deployer\invoke;
use function Deployer\get;
use function Deployer\option;
use function Deployer\run;
use function Deployer\set;
use function Deployer\task;
use function Deployer\test;
use function Deployer\writeln;

// -----------------------------------------------------------------------------
// Configurable parameters.
// -----------------------------------------------------------------------------

// Configure the custom options for the site.
option('site', null, InputOption::VALUE_REQUIRED, 'Site to deploy.');
option('unlock', null, InputOption::VALUE_NONE, 'Unlock the server before deploying.');

// Keep only the last 5 versions.
set('keep_releases', 5);

// -----------------------------------------------------------------------------
// Function for the deployment of a single site.
// -----------------------------------------------------------------------------
function deploy_site(array $config)
{
    $site = $config['name'];

    writeln("<info>🚀 Deploying $site in {{hostname}} ({{alias}})</info>");

    // Configure the current site.
    set('repository', $config['repository']);
    set('branch', $config['branch'] ?? 'main');

    set('deploy_path', $config['deploy_path'] ?? "/var/www/sites/$site");

    if (isset($config['shared_files'])) {
        set('shared_files', $config['shared_files']);
    }

    if (isset($config['shared_dirs'])) {
        set('shared_dirs', $config['shared_dirs']);
    }

    // Configure the writable directories.
    set('writable_dirs', $config['writable_dirs'] ?? ['var', 'tmp']);
    set('writable_mode', $config['writable_mode'] ?? 'chmod');
    set('writable_use_sudo', $config['writable_use_sudo'] ?? false);
    set('writable_recursive', $config['writable_recursive'] ?? true);
    set('writable_chmod_mode', $config['writable_chmod_mode'] ?? '0777');

    // Unlock the server if the option is set.
    if (input()->getOption('unlock')) {
        invoke('deploy:unlock');
    }

    // Run deploy tasks.
    invoke('deploy:check_remote');
    invoke('deploy:prepare');
    invoke('deploy:update_code');
    invoke('deploy:shared');
    invoke('deploy:writable');
    invoke('deploy:vendors');
    invoke('deploy:assets');
    invoke('deploy:symlink');
    invoke('deploy:unlock');
    invoke('deploy:cleanup');
    invoke('opcache:reset');

    writeln("<info>Site $site deployed successfully in {{hostname}} ({{alias}})</info>");
}

// -----------------------------------------------------------------------------
// Task to build the assets.
// -----------------------------------------------------------------------------
task('deploy:assets', function () {
    if (test('[ -f {{release_path}}/package.json ]')) {
        run('cd {{release_path}} && npm install && npm run build');
    }
});

// -----------------------------------------------------------------------------
// Task to reset the opcache after the deployment.
// -----------------------------------------------------------------------------
task('opcache:reset', function () {
    run('{{bin/php}} -r "if (function_exists(\'opcache_reset\')) { opcache_reset(); echo \'Opcache reset.\'; } else { echo \'Opcache reset function does not exist.\'; }"');
});

// -----------------------------------------------------------------------------
// Task for the deployment of a single site.
// -----------------------------------------------------------------------------
desc('Deploy a single site');
task('derafu:deploy:single', function () {
    $site = input()->getOption('site');

    if (empty($site)) {
        writeln("<error>You must specify a site with --site=name</error>");
        return;
    }

    $sites = get('sites');
    if (!isset($sites[$site])) {
        writeln("<error>The site '$site' is not defined in the configuration.</error>");
        return;
    }

    deploy_site(array_merge(['name' => $site], $sites[$site]));

    writeln("<info>✅ Deploy completed successfully!</info>");
});

// -----------------------------------------------------------------------------
// Task for the deployment of all sites.
// -----------------------------------------------------------------------------
desc('Deploy all sites');
task('derafu:deploy:all', function () {
    $sites = get('sites');
    if (empty($sites)) {
        writeln("<error>No sites are defined in the configuration.</error>");
        return;
    }

    foreach ($sites as $site => $config) {
        deploy_site(array_merge(['name' => $site], $config));
    }

    writeln("<info>✅ Deploy completed successfully!</info>");
});

// -----------------------------------------------------------------------------
// Task for the usage and list of sites.
// -----------------------------------------------------------------------------
desc('Usage and list of sites');
task('derafu:sites:list', function() {
    writeln("<info>Deployer for multiple sites</info>");
    writeln("");
    writeln("Usage:");
    writeln("  vendor/bin/dep derafu:sites:list");
    writeln("  vendor/bin/dep derafu:deploy:single --site=name [--hosts=server] [--user=user] [--port=port]");
    writeln("  vendor/bin/dep derafu:deploy:all [--hosts=server] [--user=user] [--port=port]");
    writeln("");
    writeln("Options:");
    writeln("  --hosts (-H): SSH server (default: localhost)");
    writeln("  --user (-u): SSH user (default: admin)");
    writeln("  --port (-p): SSH port (default: 2222)");
    writeln("  --site: Site to deploy (required for deploy:single)");
    writeln("");
    writeln("Configured sites:");

    foreach (get('sites') as $site => $config) {
        writeln("  - $site: " . $config['repository']);
    }
});
