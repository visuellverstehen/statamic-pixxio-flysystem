<?php

namespace VV\PixxioFlysystem;

use Exception;
use Generator;
use GuzzleHttp\Psr7\MimeType;
use Illuminate\Support\Str;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use VV\PixxioFlysystem\Models\PixxioDirectory;
use VV\PixxioFlysystem\Models\PixxioFile;

class PixxioAdapter implements FilesystemAdapter
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function fileExists(string $path): bool
    {
        $path = self::prefix($path);

        return (bool)PixxioFile::find($path);
    }

    public function directoryExists(string $path): bool
    {
        return (bool)PixxioDirectory::find($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        // todo: update meta data.
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            $this->client->upload($path, $contents);
        } catch (Exception $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function read(string $path): string
    {
        $path = self::prefix($path);

        if (Str::contains($path, '.meta')) {
            return $this->client->getMetaData($path);
        }

        if (!$file = PixxioFile::find($path)) {
            // todo: handle
        }

        return $this->client->read($file->absolute_path);
    }

    public function readStream(string $path)
    {
        try {
            $path = self::prefix($path);

            return $this->client->readStream($path);
        } catch (BadRequestException $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function delete(string $path): void
    {
        $path = self::prefix($path);

        if (PixxioDirectory::find($path)) {
            $this->client->deleteDirectory($path);

            return;
        }

        if (PixxioFile::find($path)) {
            $this->client->deleteFile($path);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $this->client->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->client->createDirectory($path);
        } catch (Exception $exception) {
            throw UnableToCreateDirectory::atLocation($path, $exception->getMessage());
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Adapter does not support visibility controls.');
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        return new FileAttributes($path, null, null, null, MimeType::fromFilename($path));
    }

    public function lastModified(string $path): FileAttributes
    {
        $path = self::prefix($path);

        if (!$file = PixxioFile::find($path)) {
            // todo: throw exception. Could not find file.
        }

        return new FileAttributes($path, null, null, $file->last_modified);
    }

    public function fileSize(string $path): FileAttributes
    {
        $path = self::prefix($path);

        if (!$file = PixxioFile::find($path)) {
            // todo: throw exception. Could not find file.
        }

        return new FileAttributes($path, $file->filesize);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        foreach (self::iterateFolderContents($path, $deep) as $content) {
            if (is_a($content, PixxioDirectory::class)) {
                yield new DirectoryAttributes($content->relative_path);

                continue;
            }

            // If it's not a directory it is a file!
            yield new FileAttributes(
                $content->relative_path,
                $content->filesize,
            );
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        // todo:
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        // todo:
    }

    private function iterateFolderContents(string $path = '', bool $deep = false): Generator
    {
        yield from PixxioDirectory::all();

        // Does it make sense to call all files at once? right now it is about 10.000 files/entries.
        yield from PixxioFile::all();
    }

    private function prefix($path): string
    {
        return Str::start($path, '/');
    }
}
