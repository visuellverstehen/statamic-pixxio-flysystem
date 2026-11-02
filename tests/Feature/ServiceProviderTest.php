<?php

use Illuminate\Support\Facades\Storage;
use VV\PixxioFlysystem\PixxioAdapter;

it('registers the pixxio disk driver', function () {
    expect(Storage::disk('pixxio')->getAdapter())->toBeInstanceOf(PixxioAdapter::class);
});

it('registers the file route, sync action and command', function () {
    $this->get('pixxio-file/1/file.jpg')->assertNotFound();

    expect(Statamic\Facades\Action::get('sync_asset_with_pixxio_action'))->not->toBeNull()
        ->and(Illuminate\Support\Facades\Artisan::all())->toHaveKey('pixxio:sync');
});
