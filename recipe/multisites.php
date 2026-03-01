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
option('source', null, InputOption::VALUE_OPTIONAL, 'Source of the site configuration.');
option('site', null, InputOption::VALUE_REQUIRED, 'Site to deploy.');
option('unlock', null, InputOption::VALUE_NONE, 'Unlock the server before deploying.');

// Keep only the last 5 versions.
set('keep_releases', 5);

/**
 * Get the sites configuration.
 *
 * @return array The sites configuration.
 */
function get_sites(): array
{
    $source = input()->getOption('source');

    $sites = get('sites');

    $found_sites = array_values(array_filter($sites, function ($config) use ($source) {
        return $source === null || $config['source'] === $source;
    }));

    if (empty($found_sites)) {
        if (!empty($source)) {
            throw new Exception("No sites are defined in the configuration for source '$source'.");
        }
        throw new Exception('No sites are defined in the configuration.');
    }

    return $found_sites;
}

// -----------------------------------------------------------------------------
// Functions.
// -----------------------------------------------------------------------------

/**
 * Get the site configuration.
 *
 * @param string $site The site name.
 * @return array The site configuration.
 */
function get_site(string $site): array
{
    $sites = get_sites();

    $found_sites = array_values(array_filter($sites, function ($config) use ($site) {
        return $config['name'] === $site;
    }));

    if (empty($found_sites)) {
        $source = input()->getOption('source');
        if (!empty($source)) {
            throw new Exception("The site '$site' is not defined in the configuration for source '$source'.");
        }
        throw new Exception("The site '$site' is not defined in the configuration.");
    }

    return $found_sites[0];
}

/**
 * Deploy the site.
 *
 * @param array $config The site configuration.
 */
function deploy_site(array $config)
{
    // Configure the site repository and the paths.
    configure_site_repository($config);
    configure_paths($config);

    // Show the site name.
    $site = get('site');
    writeln("<info>🚀 Deploying $site in {{hostname}} ({{alias}})</info>");

    // Show the site paths.
    writeln("<info>- Deploy path: " . get('deploy_path') . "</info>");
    writeln("<info>- Shared files: " . implode(', ', get('shared_files')) . "</info>");
    writeln("<info>- Shared directories: " . implode(', ', get('shared_dirs')) . "</info>");

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

    // Prepares a new release updating the code.
    invoke('deploy:info');
    invoke('deploy:setup');         // Creates the deploy path and other directories.
    invoke('deploy:check_remote');  // Checks if the code is already deployed.
    invoke('deploy:lock');
    invoke('deploy:release');
    invoke('deploy:update_code');

    // Run the deploy tasks.
    invoke('deploy:initial_actions');
    invoke('deploy:env');
    invoke('deploy:shared');
    invoke('deploy:writable');
    invoke('deploy:vendors');
    invoke('deploy:assets');
    invoke('deploy:final_actions');

    // Finalize the deployment.
    invoke('deploy:symlink');
    invoke('deploy:unlock');
    invoke('deploy:cleanup');
    invoke('opcache:reset');
    invoke('deploy:success_actions');
    invoke('deploy:success');

    // Show the success message.
    writeln("<info>Site $site deployed successfully in {{hostname}} ({{alias}})</info>");
}

/**
 * Configure the site repository.
 *
 * @param array $config The site configuration.
 */
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

/**
 * Configure the paths:
 *   - deploy_path
 *   - shared_files
 *   - shared_dirs
 */
function configure_paths(array $config): void
{
    // Configure the deploy path.
    $site = get('site');
    set('deploy_path', $config['deploy_path'] ?? "/var/www/sites/$site");

    // Configure the shared files, from config or from the shared directory.
    $shared_files =
        $config['shared_files'] ?? (
            test('[ -d {{deploy_path}}/shared ]')
                ? array_filter(explode("\n", trim(
                    run('find {{deploy_path}}/shared -mindepth 1 -maxdepth 1 -type f -printf "%f\n"')
                )))
                : []
            )
    ;
    set('shared_files', $shared_files);

    // Configure the shared directories, from config or from the shared directory.
    $shared_dirs =
        $config['shared_dirs'] ?? (
            test('[ -d {{deploy_path}}/shared ]')
                ? array_filter(explode("\n", trim(
                    run('find {{deploy_path}}/shared -mindepth 1 -maxdepth 1 -type d -printf "%f\n"')
                )))
                : []
            )
    ;
    set('shared_dirs', $shared_dirs);
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
// Task to run the initial actions (if defined in the .deployer/actions/initial.sh file).
// -----------------------------------------------------------------------------
task('deploy:initial_actions', function () {
    if (test('[ -f {{release_path}}/.deployer/actions/initial.sh ]')) {
        run('cd {{release_path}} && ./.deployer/actions/initial.sh');
    }
});

// -----------------------------------------------------------------------------
// Task to run the final actions (if defined in the .deployer/final_actions.sh file).
// -----------------------------------------------------------------------------
task('deploy:final_actions', function () {
    if (test('[ -f {{release_path}}/.deployer/actions/final.sh ]')) {
        run('cd {{release_path}} && ./.deployer/actions/final.sh');
    }
});

// -----------------------------------------------------------------------------
// Task to run the success actions (if defined in the .deployer/success_actions.sh file).
// -----------------------------------------------------------------------------
task('deploy:success_actions', function () {
    if (test('[ -f {{release_path}}/.deployer/actions/success.sh ]')) {
        run('cd {{release_path}} && ./.deployer/actions/success.sh');
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

    try {
        $site = get_site($site);
    } catch (Exception $e) {
        writeln("<error>{$e->getMessage()}</error>");
        return;
    }

    deploy_site($site);
    writeln("<info>✅ Deploy completed successfully!</info>");
});

// -----------------------------------------------------------------------------
// Task for the deployment of all sites.
// -----------------------------------------------------------------------------
desc('Deploy all sites');
task('derafu:deploy:all', function () {
    try {
        $sites = get_sites();
    } catch (Exception $e) {
        writeln("<error>{$e->getMessage()}</error>");
        return;
    }

    foreach ($sites as $config) {
        deploy_site($config);
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

    try {
        $sites = get_sites();
    } catch (Exception $e) {
        writeln("<error>{$e->getMessage()}</error>");
        return;
    }

    writeln("Configured sites:");
    writeln("");
    writeln(sprintf('  |-%-15s-|-%-30s-|-%-60s-|', str_repeat('-', 15), str_repeat('-', 30), str_repeat('-', 60)));
    writeln(sprintf('  | %-15s | %-30s | %-60s |', 'Source', 'Name', 'Repository'));
    writeln(sprintf('  |-%-15s-|-%-30s-|-%-60s-|', str_repeat('-', 15), str_repeat('-', 30), str_repeat('-', 60)));
    foreach ($sites as $config) {
        writeln(sprintf('  | %-15s | %-30s | %-60s |', $config['source'], $config['name'], $config['repository']));
    }
    writeln(sprintf('  |-%-15s-|-%-30s-|-%-60s-|', str_repeat('-', 15), str_repeat('-', 30), str_repeat('-', 60)));
});
