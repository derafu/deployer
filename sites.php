<?php

declare(strict_types=1);

/**
 * Derafu: Foundation - Base for Derafu's Projects.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

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
return [
    'www.example.com' => 'git@github.com:example/example.git',
];
