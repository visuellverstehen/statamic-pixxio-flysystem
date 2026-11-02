<?php

use Illuminate\Support\Facades\Http;
use VV\PixxioFlysystem\Models\PixxioFile;

it('skips files that conflict with existing or already synced files', function () {
    PixxioFile::create(['pixxio_id' => 1, 'relative_path' => '/a.jpg', 'absolute_path' => 'https://cdn.pixxio.test/1/a.jpg']);

    Http::fake(['*/files*' => Http::response([
        'success' => true,
        'cursor' => null,
        'files' => [
            pixxioFile(2, 'a.jpg'),           // path taken by another id
            pixxioFile(1, 'b.jpg'),           // id already has another path
            pixxioFile(3, 'c.jpg', '/dir'),
            pixxioFile(4, 'c.jpg', '/dir'),   // path taken earlier in this run
        ],
    ])]);

    $this->artisan('pixxio:sync')->assertSuccessful();

    expect(PixxioFile::orderBy('pixxio_id')->pluck('relative_path', 'pixxio_id')->all())
        ->toBe([1 => '/a.jpg', 3 => '/dir/c.jpg']);
});
