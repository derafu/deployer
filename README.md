# Derafu: Deployer - PHP Deployer for multiple sites

![GitHub last commit](https://img.shields.io/github/last-commit/derafu/deployer/main)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/derafu/deployer)
![GitHub Issues](https://img.shields.io/github/issues-raw/derafu/deployer)
![Total Downloads](https://poser.pugx.org/derafu/deployer/downloads)
![Monthly Downloads](https://poser.pugx.org/derafu/deployer/d/monthly)

Derafu Deployer is a PHP deployment tool built on top of [Deployer](https://deployer.org/) that simplifies managing deployments for multiple websites on a single server.

## Features

- Deploy multiple sites from a single configuration.
- Deploy individual sites or all sites at once.
- Support for different deployment environments (development, production).
- Shared files and directories between releases.
- Writable directories configuration.
- Asset building support for sites with Node.js/npm.
- OPcache reset after deployments.
- Simple and flexible configuration.
- Support for different Git branches per site.

## Requirements

- PHP 8.3 or higher.
- SSH access to your servers.
- Git repositories for your projects.

## Installation

```bash
composer create-project derafu/deployer
```

**Note**: The tool is designed to be used standalone, not inside other project.

## Configuration

### Sites Configuration

Edit the `sites.php` file to configure your websites. The key of the array is the site/domain name and the value can be:

- A simple string with the repository URL.
- An array with detailed configuration options.

```php
<?php
return [
    // Simple configuration with just the repository URL.
    'www.example.com' => 'git@github.com:example/example.git',

    // Extended configuration with options.
    'www.complex-site.com' => [
        'repository' => 'git@github.com:example/complex-site.git',
        'branch' => 'develop',
        'deploy_path' => '/var/www/custom/path/complex-site',
        'shared_files' => ['.env', 'config/settings.php'],
        'shared_dirs' => ['var/uploads', 'var/logs'],
        'writable_dirs' => ['var', 'tmp', 'var/cache'],
        'writable_mode' => 'chmod',
        'writable_use_sudo' => false,
        'writable_recursive' => true,
        'writable_chmod_mode' => '0775',
    ],
];
```

### Available Configuration Options

| Option              | Description                                  | Default                    |
|---------------------|----------------------------------------------|----------------------------|
| repository          | Git repository URL                           | *Required*                 |
| branch              | Git branch to deploy                         | main                       |
| deploy_path         | Deployment path on server                    | /var/www/sites/[site-name] |
| shared_files        | Files to share between releases              | []                         |
| shared_dirs         | Directories to share between releases        | []                         |
| writable_dirs       | Directories to make writable                 | ['var', 'tmp']             |
| writable_mode       | Mode for writable directories                | chmod                      |
| writable_use_sudo   | Whether to use sudo for writable directories | false                      |
| writable_recursive  | Apply writable permissions recursively       | true                       |
| writable_chmod_mode | Chmod mode for writable directories          | 0777                       |

### Server Configuration

The server configuration is defined in `deploy.php`. By default, a development environment on localhost and an optional production environment are configured:

```php
// Default development environment.
host('localhost')
    ->setRemoteUser('admin')
    ->setPort(2222)
    ->setLabels(['stage' => 'dev']);

// Production environment (only if DEPLOYER_HOST is set).
if (getenv('DEPLOYER_HOST')) {
    host(getenv('DEPLOYER_HOST'))
        ->setRemoteUser(getenv('DEPLOYER_USER') ?: 'admin')
        ->setPort(getenv('DEPLOYER_PORT') ?: 2222)
        ->setLabels(['stage' => 'prod']);
    set('default_selector', 'stage=prod');
}
```

You can modify these settings or add additional environments as needed.

## Usage

### List Available Sites

To see the list of configured sites and usage information:

```bash
vendor/bin/dep derafu:sites:list
```

### Deploy a Single Site

#### Development Environment (localhost)

```bash
vendor/bin/dep derafu:deploy:single --site=www.example.com
```

#### Production Environment

```bash
DEPLOYER_HOST=hosting.example.com vendor/bin/dep derafu:deploy:single --site=www.example.com
```

You can also specify the SSH user and port:

```bash
DEPLOYER_HOST=hosting.example.com DEPLOYER_USER=deployuser DEPLOYER_PORT=22 vendor/bin/dep derafu:deploy:single --site=www.example.com
```

### Deploy All Sites

#### Development Environment (localhost)

```bash
vendor/bin/dep derafu:deploy:all
```

#### Production Environment

```bash
DEPLOYER_HOST=hosting.example.com vendor/bin/dep derafu:deploy:all
```

### Unlock a Deployment

If a deployment gets stuck or locked, you can unlock it:

```bash
DEPLOYER_HOST=hosting.example.com vendor/bin/dep derafu:deploy:single --site=www.example.com --unlock
```

## Deployment Process

For each site, the deployment process performs the following steps:

1. **Check Remote**: Verifies SSH connection and deployment path.
2. **Prepare**: Creates required directories if they don't exist.
3. **Update Code**: Fetches code from the Git repository.
4. **Shared Files/Dirs**: Links shared files and directories.
5. **Writable Dirs**: Makes specified directories writable.
6. **Vendors**: Installs PHP dependencies with Composer.
7. **Assets**: Builds frontend assets if package.json exists (`npm install && npm run build`).
8. **Symlink**: Creates a symlink to the new release.
9. **Unlock**: Removes the deployment lock.
10. **Cleanup**: Removes old releases (keeps 5 by default).
11. **OPcache Reset**: Resets the OPcache.

## Customization

You can extend the deployer recipe by editing the `multisites.php` file to add custom tasks or modify existing ones.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
