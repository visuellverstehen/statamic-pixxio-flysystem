<?php

use League\Flysystem\UnableToReadFile;
use VV\PixxioFlysystem\Client;
use VV\PixxioFlysystem\PixxioAdapter;

it('throws when reading a file that does not exist', function () {
    (new PixxioAdapter(new Client()))->read('missing.jpg');
})->throws(UnableToReadFile::class);
