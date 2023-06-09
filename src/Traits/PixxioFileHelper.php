<?php

namespace VV\PixxioFlysystem\Traits;

use VV\PixxioFlysystem\Models\PixxioFile;
use VV\PixxioFlysystem\Utilities\PixxioFileMapper;

trait PixxioFileHelper
{
    private function getRelativePath(array $fileData): string
    {
        $directory = $fileData['category'] ?? '';

        return "{$directory}/{$fileData['originalFilename']}";
    }

    private function createPixxioFile(array $fileData): PixxioFile
    {
        $preparedData = (new PixxioFileMapper($fileData))->toArray();

        return PixxioFile::create($preparedData);
    }
}