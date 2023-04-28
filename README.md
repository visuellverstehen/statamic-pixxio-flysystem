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
        'api_key' => env('PIXXIO_API_KEY'),
        'refresh_token' => env('PIXXIO_REFRESH_TOKEN'),
        'endpoint' => env('PIXXIO_ENDPOINT'),
    ],
],
```

### 2. Run migrations

``` bash
php artisan migrate
```

### 3. Edit blueprint

Add `alt` and `copyright` fields to the asset-container blueprint.

### 4. Run synchronization script:

``` bash
php artisan pixxio:sync
```

In order to keep the database updated schedule a task that runs synchronization script regularly.

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

---
## How to Use

- Simply create an Asset-Container with pixxio as your driver.

