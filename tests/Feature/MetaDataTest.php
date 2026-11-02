<?php

use Statamic\Facades\YAML;
use League\Flysystem\Config;
use VV\PixxioFlysystem\Client;
use VV\PixxioFlysystem\PixxioAdapter;
use VV\PixxioFlysystem\Models\PixxioFile;
use VV\PixxioFlysystem\Utilities\PixxioFileMapper;

it('maps pixxio metadata to raw values', function () {
    $data = pixxioFile(1, 'a.jpg', '/dir') + ['description' => 'Desc: with colon'];
    $data['metadataFields'] = [
        ['name' => 'Alternativetext', 'value' => 'Alt # text'],
        ['name' => 'CopyrightNotice', 'value' => '© ACME'],
        ['name' => 'Fotograf', 'value' => 'Jane'],
    ];

    expect((new PixxioFileMapper($data))->toArray())->toMatchArray([
        'relative_path' => '/dir/a.jpg',
        'alternative_text' => 'Alt # text',
        'copyright' => '© ACME, Jane',
        'description' => 'Desc: with colon',
    ]);
});

it('round-trips metadata containing yaml syntax', function () {
    PixxioFile::create([
        'pixxio_id' => 1,
        'relative_path' => '/a.jpg',
        'absolute_path' => 'https://cdn.pixxio.test/1/a.jpg',
        'description' => "Line one\nline: two",
    ]);
    $adapter = new PixxioAdapter(new Client());

    $adapter->write('.meta/a.jpg.yaml', YAML::dump(['data' => [
        'alt' => 'Alt: with # chars',
        'copyright' => "'quoted'",
        'focus' => '50-50-1',
    ]]), new Config());

    expect(YAML::parse($adapter->read('.meta/a.jpg.yaml'))['data'])->toBe([
        'alt' => 'Alt: with # chars',
        'copyright' => "'quoted'",
        'description' => "Line one\nline: two",
        'focus' => '50-50-1',
    ]);
});
