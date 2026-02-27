<?php

declare(strict_types=1);

/**
 * Derafu: Foundation - Base for Derafu's Projects.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

use Symfony\Component\Yaml\Yaml;

/**
 * Sites configuration.
 *
 * The key of the array is the site/domain name and the value can be:
 *
 *   - The repository URL.
 *   - An array of options.
 *
 * If the value is an array, the following options are available:
 *
 *   - branch: The branch to deploy.
 *   - deploy_path: The path to deploy the code in the server.
 *   - shared_files: List of files what will be shared between releases.
 *   - shared_dirs: List of dirs what will be shared between releases.
 *   - writable_dirs: The directories to make writable.
 *   - writable_mode: The mode to make the directories writable.
 *   - writable_use_sudo: Whether to use sudo to make the directories writable.
 *   - writable_recursive: Whether to make the directories writable recursively.
 *   - writable_chmod_mode: The mode to make the directories writable.
 *
 * @return array
 */

// Sites configuration.
// It's highly recommended to use the sites.yaml files to configure the sites.
$sites = [
    // 'www.example.com' => 'git@github.com:example/example.git',
];

/**
 * Normalize the sites configuration.
 *
 * @param array $sites The sites configuration.
 * @param string $source The source of the sites configuration.
 * @return array The normalized sites configuration.
 */
function normalize_sites(array $sites, string $source): array
{
    $normalized = [];

    foreach ($sites as $site => $config) {
        // If the config is a string, convert it to an array.
        if (is_string($config)) {
            $config = [
                'repository' => $config,
            ];
        }

        // Add the source and name to the config.
        $config = array_merge([
            'source' => $source,
            'name' => !is_numeric($site) ? $site : null,
        ], $config);

        // Ensure default values exist.
        $config['shared_dirs'] = $config['shared_dirs'] ?? ['var', 'tmp'];
        $config['writable_use_sudo'] = $config['writable_use_sudo'] ?? true;

        // Add, without string key, the domain config to the normalized array.
        $normalized[] = $config;
    }

    // Return the normalized sites.
    return $normalized;
}

// Normalize PHP array of sites.
$sites = normalize_sites($sites, 'php');

// Load the sites configuration from YAML files and normalize them.
$sites_yaml_files = glob(__DIR__ . '/config/*sites.yaml');
foreach ($sites_yaml_files as $sites_yaml_file) {
    $loaded_sites = Yaml::parseFile($sites_yaml_file);
    $source = rtrim(basename($sites_yaml_file, 'sites.yaml') ?: 'yaml', '.');
    $normalized_sites = normalize_sites($loaded_sites, $source);
    $sites = array_merge($sites, $normalized_sites);
}

// Return the sites configuration.
return $sites;
