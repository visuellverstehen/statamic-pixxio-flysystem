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
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
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

        return $this->client->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        $path = self::prefix($path);

        return $this->client->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $path = self::prefix($path);

        try {
            $this->client->setMetaData($path, $contents);
        } catch (Exception $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage());
        }
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
            try {
                return $this->client->getMetaData($path);
            } catch (Exception $exception) {
                throw new Exception($exception->getMessage());
            }
        }

        if (!$file = PixxioFile::find($path)) {
            UnableToReadFile::fromLocation($path, "Could not find file '{$path}'");
        }

        return $this->client->read($file->absolute_path);
    }

    public function readStream(string $path)
    {
        try {
            $path = self::prefix($path);

            return $this->client->readStream($path);
        } catch (Exception $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function delete(string $path): void
    {
        $path = self::prefix($path);

        try {
            $this->client->deleteFile($path);
        } catch (Exception $exception) {
            throw UnableToDeleteFile::atLocation($path, $exception->getMessage());
        }
    }

    public function deleteDirectory(string $path): void
    {
        $path = self::prefix($path);

        try {
            $this->client->deleteDirectory($path);
        } catch (Exception $exception) {
            throw UnableToDeleteDirectory::atLocation($path, $exception->getMessage());
        }
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
        // Not supported.
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

        return new FileAttributes($path, null, null, PixxioFile::find($path)->last_modified ?? now()->timestamp);
    }

    public function fileSize(string $path): FileAttributes
    {
        $path = self::prefix($path);

        return new FileAttributes($path, PixxioFile::find($path)->filesize ?? 0);
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
        // Not supported.
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        // Not supported.
    }

    public function getUrl(string $path): string
    {
        $path = self::prefix($path);

        if ($path === '/') {
            return $path;
        }

        if (!$file =  PixxioFile::find($path)) {
            return $path;
        }

        return $this->buildUrl($file);
    }

    private function iterateFolderContents(string $path = '', bool $deep = false): Generator
    {
        yield from PixxioDirectory::all();
        yield from PixxioFile::all();
    }

    private function prefix($path): string
    {
        return Str::start($path, '/');
    }

    private function buildUrl(PixxioFile $file): string
    {
        $pathInfo = pathinfo($file->absolute_path);
        $extension = $pathInfo['extension'];
        $url = config('app.url');

        return "{$url}pixxio-file/{$file->pixxio_id}/file.{$extension}";
    }
}
