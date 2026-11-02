<?php

use Illuminate\Support\Facades\Http;
use VV\PixxioFlysystem\Models\PixxioFile;

it('only creates files that do not exist yet', function () {
    PixxioFile::create(['pixxio_id' => 1, 'relative_path' => '/a.jpg', 'absolute_path' => 'https://cdn.pixxio.test/1/a.jpg']);

    Http::fake(['*/files*' => Http::sequence()
        ->push([
            'success' => true,
            'cursor' => 'next',
            'files' => [pixxioFile(1, 'a.jpg'), pixxioFile(2, 'a.jpg'), pixxioFile(3, 'b.jpg'), pixxioFile(4, 'b.jpg')],
        ])
        ->push([
            'success' => true,
            'cursor' => null,
            'files' => [pixxioFile(3, 'b.jpg'), pixxioFile(5, 'c.jpg')],
        ]),
    ]);

    $this->artisan('pixxio:sync --new')
        ->expectsOutputToContain('2 new files have been found.');

    expect(PixxioFile::orderBy('pixxio_id')->pluck('relative_path', 'pixxio_id')->all())
        ->toBe([1 => '/a.jpg', 3 => '/b.jpg', 5 => '/c.jpg']);
    Http::assertSentCount(2);
});
