<?php

use Illuminate\Support\Facades\Http;
use VV\PixxioFlysystem\Models\PixxioFile;

function pixxioFile(int $id, string $fileName, string $directory = ''): array
{
    return [
        'id' => $id,
        'fileName' => $fileName,
        'directory' => ['path' => $directory],
        'originalFileURL' => "https://cdn.pixxio.test/{$id}/{$fileName}",
        'fileSize' => 100,
        'width' => 10,
        'height' => 10,
        'uploadDate' => '2026-01-01 00:00:00',
        'metadataFields' => [],
    ];
}

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
