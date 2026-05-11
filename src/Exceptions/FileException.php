<?php

namespace VV\PixxioFlysystem\Exceptions;

use Exception;

class FileException extends Exception
{
    public static function notFound($path): static
    {
        return new static("No file could be found at '{$path}'.");
    }
}
