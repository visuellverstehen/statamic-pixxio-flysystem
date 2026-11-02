# Statamic Pixxio Flysystem

Use your [pixx.io](https://www.pixx.io) Mediaspace as a read-only Statamic asset container.

A sync command indexes pixx.io files and folders in your database. Statamic browses that index. File URLs redirect to the original files on pixx.io.

- Statamic 3.4–6
- Only web images are synced
- Read-only: uploading, moving, renaming and deleting on pixx.io is not supported

## Installation

```bash
composer require visuellverstehen/statamic-pixxio-flysystem
php artisan migrate
```

Add a disk named `pixxio` to `config/filesystems.php`:

```php
'disks' => [
    'pixxio' => [
        'driver' => 'pixxio',
        'url' => env('PIXXIO_URL'),                   // API base URL of your Mediaspace
        'access_token' => env('PIXXIO_ACCESS_TOKEN'), // API key, sent as Bearer token
    ],
],
```

Create an asset container with the handle `pixxio` on the `pixxio` disk. Both names are required.

Add `alt`, `copyright` and `description` fields to the container's blueprint.

Run the initial sync:

```bash
php artisan pixxio:sync
```

## Syncing

| Command | What it does |
|---|---|
| `php artisan pixxio:sync` | Syncs all files and folders. Removes entries that haven't been seen in pixx.io for 24 hours. |
| `php artisan pixxio:sync --new` | Only adds files uploaded today. |

To sync on a schedule, set cron expressions in the config (requires Laravel's scheduler):

```php
'schedule' => [
    'sync' => '0 3 * * *',
    'sync_new' => '*/15 * * * *',
],
```

Single assets can be re-synced in the Control Panel with the **Synchronize with pixx.io** action.

## Metadata

| Asset field | Source in pixx.io |
|---|---|
| `alt` | Metadata field `Alternativetext` |
| `copyright` | Metadata fields `CopyrightNotice` and `Fotograf` |
| `description` | File description |

Changes to `alt`, `copyright` and the focal point in the Control Panel are stored locally and not written back to pixx.io. The next full sync overwrites `alt` and `copyright`.

## Configuration

```bash
php artisan vendor:publish --provider="VV\PixxioFlysystem\ServiceProvider" --tag="flysystem-pixxio-config"
```

This publishes `config/statamic/flysystem-pixxio.php`:

| Key | Default | Description |
|---|---|---|
| `exclude.directories` | `[]` | Folder paths to skip, e.g. `'/home'` |
| `verify_ssl_certificate` | `true` | Verify the pixx.io SSL certificate |
| `schedule.sync` | `null` | Cron expression for `pixxio:sync` |
| `schedule.sync_new` | `null` | Cron expression for `pixxio:sync --new` |

## Testing

```bash
composer install
vendor/bin/pest
```

## More about us

[visuellverstehen](https://visuellverstehen.de) is a digital agency building websites, apps and brands with a focus on the common good. We are an official Statamic partner.

## License

MIT. See [LICENSE.md](LICENSE.md).
