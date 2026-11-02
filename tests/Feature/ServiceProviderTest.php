<?php

use Illuminate\Support\Facades\Storage;
use VV\PixxioFlysystem\PixxioAdapter;

it('registers the pixxio disk driver', function () {
    expect(Storage::disk('pixxio')->getAdapter())->toBeInstanceOf(PixxioAdapter::class);
});
