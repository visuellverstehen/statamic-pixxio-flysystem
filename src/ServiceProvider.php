<?php

namespace VV\PixxioFlysystem;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
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
            ->bootAddonPixxioDriver();
    }

    public function bootAddonConfig()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'statamic.flysystem-pixxio');

        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('statamic/flysystem-pixxio.php'),
        ], 'flysystem-pixxio-config');

        return $this;
    }

    public function bootAddonPixxioDriver()
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

    public function bootAddonMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        return $this;
    }

    public function bootAddonCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncWithPixxio::class,
            ]);
        }

        return $this;
    }
}
