<?php

namespace VV\PixxioFlysystem;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use Statamic\Providers\AddonServiceProvider;
use VV\PixxioFlysystem\Actions\SyncAssetWithPixxioAction;
use VV\PixxioFlysystem\Console\SyncWithPixxio;

class ServiceProvider extends AddonServiceProvider
{
    public function boot()
    {
        parent::boot();

        $this
            ->bootAddonActions()
            ->bootAddonConfig()
            ->bootAddonCommands()
            ->bootAddonMigrations()
            ->bootAddonRoutes()
            ->bootAddonTranslations()
            ->bootAddonMacros()
            ->bootAddonPixxioDriver()
            ->overrideAssetClass();
    }

    public function bootAddonConfig(): self
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'statamic.flysystem-pixxio');

        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('statamic/flysystem-pixxio.php'),
        ], 'flysystem-pixxio-config');

        return $this;
    }

    public function bootAddonActions(): self
    {
        SyncAssetWithPixxioAction::register();

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

    public function bootAddonRoutes(): self
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

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

    public function bootAddonTranslations(): self
    {
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');

        return $this;
    }

    public function bootAddonMacros(): self
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

    public function overrideAssetClass(): self
    {
        $this->app->bind(\Statamic\Contracts\Assets\Asset::class, \VV\PixxioFlysystem\Assets\Asset::class);

        return $this;
    }
}
