<?php

namespace VV\PixxioFlysystem;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use Statamic\Providers\AddonServiceProvider;
use VV\PixxioFlysystem\Console\SyncWithPixxio;

class ServiceProvider extends AddonServiceProvider
{
    public function boot()
    {
        parent::boot();

        $this
            ->bootAddonConfig()
            ->bootAddonCommands()
            ->bootAddonMigrations()
            ->bootMacros()
            ->bootAddonPixxioDriver();
    }

    public function bootAddonConfig(): self
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'statamic.flysystem-pixxio');

        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('statamic/flysystem-pixxio.php'),
        ], 'flysystem-pixxio-config');

        return $this;
    }

    public function bootAddonPixxioDriver(): self
    {
        Storage::extend('pixxio', function (Application $app, array $config) {
            $adapter = new PixxioAdapter(new Client());

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });

        return $this;
    }

    public function bootAddonMigrations(): self
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    public function bootAddonCommands(): self
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncWithPixxio::class,
            ]);
        }

        return $this;
    }

    public function bootMacros(): self
    {
        Http::macro('pixxio', function () {
            $endpoint = config('filesystems.disks.pixxio.endpoint', '');

            if (config('statamic.flysystem-pixxio.verify_ssl_certificate', true)) {
                return Http::baseUrl($endpoint);
            }

            return Http::withoutVerifying()->baseUrl($endpoint);
        });

        return $this;
    }
}
