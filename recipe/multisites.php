<?php

declare(strict_types=1);

/**
 * Derafu: Deployer - PHP Deployer for multiple sites
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
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

// Configure the default shared files.
set('default_shared_files', ['.env']);

// Configure the default shared directories.
set('default_shared_dirs', []);

// -----------------------------------------------------------------------------
// Function for the deployment of a single site.
// -----------------------------------------------------------------------------
function deploy_site(array $config)
{
    // Configure the site repository.
    configure_site_repository($config);
    $site = get('site');

    // Write the start message.
    writeln("<info>🚀 Deploying $site in {{hostname}} ({{alias}})</info>");

    // Configure the paths.
    configure_paths($config);

    // Configure the writable directories.
    set('writable_dirs', $config['writable_dirs'] ?? ['var', 'tmp']);
    set('writable_mode', $config['writable_mode'] ?? 'chmod');
    set('writable_use_sudo', $config['writable_use_sudo'] ?? false);
    set('writable_recursive', $config['writable_recursive'] ?? true);
    set('writable_chmod_mode', $config['writable_chmod_mode'] ?? '0777');

    // Configure the composer options.
    set('composer_options', '--no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --ignore-platform-req=ext-xdebug');

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

function configure_site_repository(array $config): void
{
    // Get the site name (domain name).
    if (empty($config['name'])) {
        throw new Exception("Name is required for site.");
    }
    set('site', $config['name']);

    // Get the repository of the site.
    if (empty($config['repository'])) {
        $site = get('site');
        throw new Exception("Repository is required for site $site");
    }
    if (!str_contains($config['repository'], '::')) {
        $config['repository'] = $config['repository'] . '::main';
    }
    [$repository, $branch] = explode('::', $config['repository'] );

    // Configure the repository and the branch.
    set('repository', $repository );
    set('branch', $config['branch'] ?? $branch);
}

function configure_paths(array $config): void {
    // Configure the deploy path.
    $site = get('site');
    set('deploy_path', $config['deploy_path'] ?? "/var/www/sites/$site");

    // Configure the shared files with default values.
    $default_shared_files = get('default_shared_files');
    set('shared_files', $config['shared_files'] ?? []);
    foreach ($default_shared_files as $default_shared_file) {
        if (!in_array($default_shared_file, get('shared_files'))) {
            $default_shared_file_path = get('deploy_path') . '/shared/' . $default_shared_file;
            if (test("[ -f $default_shared_file_path ]")) {
                set('shared_files', array_merge(get('shared_files'), [$default_shared_file]));
            }
        }
    }

    // Configure the shared directories with default values.
    $default_shared_dirs = get('default_shared_dirs');
    set('shared_dirs', $config['shared_dirs'] ?? []);
    foreach ($default_shared_dirs as $default_shared_dir) {
        if (!in_array($default_shared_dir, get('shared_dirs'))) {
            $default_shared_dir_path = get('deploy_path') . '/shared/' . $default_shared_dir;
            if (test("[ -d $default_shared_dir_path ]")) {
                set('shared_dirs', array_merge(get('shared_dirs'), [$default_shared_dir]));
            }
        }
    }
}

// -----------------------------------------------------------------------------
// Task to build the assets.
// -----------------------------------------------------------------------------
task('deploy:assets', function () {
    if (!test('[ -f {{release_path}}/package.json ]')) {
        return;
    }

    if (test('[ -f {{release_path}}/package-lock.json ]')) {
        run('cd {{release_path}} && npm ci && npm run build');
    } else {
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
