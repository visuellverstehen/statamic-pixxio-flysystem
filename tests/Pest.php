<?php

uses(VV\PixxioFlysystem\Tests\TestCase::class)->in(__DIR__);

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
