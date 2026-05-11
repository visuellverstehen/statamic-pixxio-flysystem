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
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

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
        $this->loadJsonTranslationsFrom(__DIR__ . '/../resources/lang');

        return $this;
    }

    public function bootAddonMacros(): self
    {
        Http::macro('pixxio', function () {
            $url = config('filesystems.disks.pixxio.url', '');
            $accessToken = config('filesystems.disks.pixxio.access_token');

            if (config('statamic.flysystem-pixxio.verify_ssl_certificate', true)) {
                return Http::baseUrl($url);
            }

            return Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                ])
                ->baseUrl($url);
        });

        return $this;
    }

    public function overrideAssetClass(): self
    {
        $this->app->bind(\Statamic\Contracts\Assets\Asset::class, \VV\PixxioFlysystem\Assets\Asset::class);

        return $this;
    }
}
