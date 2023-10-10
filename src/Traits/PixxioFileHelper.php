<?php

namespace VV\PixxioFlysystem\Traits;

use VV\PixxioFlysystem\Models\PixxioFile;
use VV\PixxioFlysystem\Utilities\PixxioFileMapper;

trait PixxioFileHelper
{
    public function getRelativePath(array $fileData): string
    {
        $directory = $fileData['category'] ?? '';

        return "{$directory}/{$fileData['originalFilename']}";
    }

    public function createPixxioFile(array $fileData): PixxioFile
    {
        $preparedData = (new PixxioFileMapper($fileData))->toArray();

        return PixxioFile::create($preparedData);
    }
}