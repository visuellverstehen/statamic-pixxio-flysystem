<?php

namespace VV\PixxioFlysystem\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use VV\PixxioFlysystem\ServiceProvider;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;
    use RefreshDatabase;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function defineEnvironment($app)
    {
        $app['config']->set('filesystems.disks.pixxio', [
            'driver' => 'pixxio',
            'access_token' => 'test-token',
            'url' => 'https://pixxio.test/api/v1',
        ]);
    }
}
