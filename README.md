# Statamic Pixxio Flysystem

![Statamic 3.4+](https://img.shields.io/badge/Statamic-3.4+-FF269E?style=for-the-badge&link=https://statamic.com)

> Statamic Pixxio Flysystem is a Statamic addon that does something pretty neat.

## Features
- Provide a Flysystem Adapter for Pixxio
- Exclude specific directories from the asset-container.

---
## Licensing

Pixxio Flysystem is a commercial addon - you **must purchase a license** via the [Statamic Marketplace](https://statamic.com/addons/visuellverstehen/pixxio-flysystem) to use it in a production environment.

---

## How to Install

### Run the following command from your project root:

``` bash
composer require visuellverstehen/statamic-pixxio-flysystem
```

### Add your Pixxio credentials to config/filesystems.php

``` php
'disks' => [
    'pixxio' => [
        'driver' => 'pixxio',
        'access_token' => env('PIXXIO_ACCESS_TOKEN'),
        'url' => env('PIXXIO_URL'),
        'endpoint' => env('PIXXIO_ENDPOINT'),
    ],
],
```

### Run migrations

``` bash
php artisan migrate
```

### Edit blueprint

Add `alt`, `copyright` and `description` fields to the asset-container blueprint.

### Run synchronization script:

``` bash
php artisan pixxio:sync
```
If you wish to only import newly added files you can use the `--new` option.
``` bash
php artisan pixxio:sync --new
```

In order to keep the database updated schedule a task that runs synchronization script regularly.

### Upgrading

After updating the package, run `php artisan pixxio:sync` once to normalize stored metadata.

---
## Configurations

``` bash
php artisan vendor:publish --provider="VV\PixxioFlysystem\ServiceProvider" --tag="flysystem-pixxio-config"
```

Exclude certain directories in config/flysystem-pixxio.php

``` php
'exclude' => [
        'directories' => [
            '/home',
        ],
    ],
```

SSL certificates of the Pixxio API are verified by default. Disable it only if necessary:

``` php
'verify_ssl_certificate' => false,
```

---
## How to Use

- Simply create an Asset-Container with pixxio as your driver.

