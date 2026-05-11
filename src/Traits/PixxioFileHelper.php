<?php

namespace VV\PixxioFlysystem\Traits;

use VV\PixxioFlysystem\Models\PixxioFile;
use VV\PixxioFlysystem\Utilities\PixxioFileMapper;

trait PixxioFileHelper
{
    public function getRelativePath(array $fileData): string
    {
        $directory = data_get($fileData, 'directory.path', '');
        $fileName = data_get($fileData, 'fileName');

        return "{$directory}/{$fileName}";
    }

    public function createPixxioFile(array $fileData): PixxioFile
    {
        $preparedData = (new PixxioFileMapper($fileData))->toArray();

        return PixxioFile::create($preparedData);
    }
}
