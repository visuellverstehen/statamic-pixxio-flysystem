<?php

namespace VV\PixxioFlysystem;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use Statamic\Facades\YAML;
use VV\PixxioFlysystem\Exceptions\FileException;
use VV\PixxioFlysystem\Models\PixxioDirectory;
use VV\PixxioFlysystem\Models\PixxioFile;
use VV\PixxioFlysystem\Traits\PixxioFileHelper;

class Client
{
    use PixxioFileHelper;

    const FORMAT_TYPE = 'webimage';
    const SHOW_VERSIONS = 'false';
    const FIELDS = [
        'id', 'category', 'originalPath',
        'imagePath', 'links',
        'originalFilename', 'formatType',
        'fileSize', 'fileType', 'description',
        'uploadDate', 'createDate', 'imageHeight',
        'imageWidth', 'subject', 'dynamicMetadata',
    ];

    protected bool $verifySSLCertificate;

    public function __construct()
    {
        $this->verifySSLCertificate = config('statamic.flysystem-pixxio.verify_ssl_certificate', true);
    }

    public function fileExists(string $path): bool
    {
        return (bool)PixxioFile::find($path);
    }

    public function directoryExists(string $path): bool
    {
        return (bool)PixxioDirectory::find($path);
    }

    public function createDirectory($path): void
    {
        // prepare path for request.
        $path = trim($path, '/');

        $slashCount = Str::substrCount($path, '/');
        $length = strlen($path);
        $pos = strripos($path, '/');

        // Handle root directory
        $rootDirectory = $slashCount > 0
            ? Str::start(Str::substr($path, 0, $pos), '/')
            : 'root';

        if ($rootDirectory !== 'root') {
            // Check if root directory exists.
            if (!self::directoryExists($rootDirectory)) {
                // Do not try to create directory.
                throw new Exception("Root directory '{$rootDirectory}' does not exist.");
            }
        }

        // Define directory name
        $directoryName = $slashCount > 0
            ? Str::substr($path, $pos + 1, $length)
            : Str::substr($path, 0, $length);

        $response = Http::pixxio()
            ->post("/categories", [
                'options' => json_encode([
                    'categoryName' => $directoryName,
                    'rootCategory' => $rootDirectory,
                ]),
            ]);

        if ($response->json()['success'] !== 'true') {
            throw new Exception($response->json()['message']);
        }

        PixxioDirectory::create([
            'relative_path' => Str::start($path, '/'),
        ]);
    }

    public function deleteFile($path): void
    {
        if (!$file = PixxioFile::find($path)) {
            throw UnableToDeleteFile::atLocation($path, 'File could not be found in database');
        }

        $response = Http::pixxio()
            ->delete("/files/{$file->pixxio_id}");

        if ($response->json()['success'] !== 'true') {
            throw UnableToDeleteFile::atLocation($path, $response->json()['message']);
        }

        $file->delete();
    }

    public function deleteDirectory($path): void
    {
        if (!$directory = PixxioDirectory::find($path)) {
            throw new Exception("Could not find directory {$path}");
        }

        $options = json_encode([
            'destinationCategory' => $path,
        ]);

        $urlEncodedOptions = urlencode($options);

        $response = Http::pixxio()
            ->delete("/categories/?options={$urlEncodedOptions}");

        if ($response->json()['success'] !== 'true') {
            throw new Exception($response->json()['message']);
        }

        $directory->delete();
    }

    public function upload($path, $contents): PixxioFile
    {
        error_clear_last();
        $fileContents = @stream_get_contents($contents);

        if ($fileContents === false) {
            throw UnableToReadFile::fromLocation($path, error_get_last()['message'] ?? '');
        }

        $lastSlash = strrpos($path, '/');
        $strLength = strlen($path);

        $directory = substr($path, 0, $lastSlash);
        $fileName = trim(substr($path, $lastSlash, $strLength), '/');

        $response = Http::pixxio()
            ->attach('file', $fileContents, $fileName)
            ->post("{$this->endpoint}/files", [
                'options' => json_encode([
                    'category' => $directory,
                    'forceConversion' => 'true',
                ]),
            ]);

        if ($response->json()['success'] !== 'true') {
            return false;
        }

        // add new file to database
        $fileId = $response->json()['fileId'];

        $fileResponse = Http::pixxio()
            ->get("/files/{$fileId}", [
                'options' => json_encode([
                    'fields' => self::FIELDS
                ])
            ]);

        $fileData = $fileResponse->json();

        return self::createPixxioFile($fileData);
    }

    public function read($path): string
    {
        error_clear_last();
        $contents = @file_get_contents(
            $path,
            false,
            self::streamContext(),
        );

        if ($contents === false) {
            throw UnableToReadFile::fromLocation($path, error_get_last()['message'] ?? '');
        }

        return $contents;
    }

    public function readStream(string $path)
    {
        if (!$file = PixxioFile::find($path)) {
            throw FileException::notFound($path);
        }

        error_clear_last();
        $contents = @fopen($file->absolute_path, 'rb', false, self::streamContext());

        if ($contents === false) {
            throw UnableToReadFile::fromLocation($path, error_get_last()['message'] ?? '');
        }

        return $contents;
    }

    public function getMetaData($path): string
    {
        $path = str_replace(['.meta/', '.yaml'], '', $path);

        if (!$file = PixxioFile::find($path)) {
            throw FileException::notFound($path);
        }

        return <<<EOD
            data:
              alt: {$file->alternative_text}
              copyright: {$file->copyright}
              description: {$file->description}
              focus: {$file->focus}
            size: {$file->filesize}
            last_modified: {$file->last_modified}
            width: {$file->width}
            height: {$file->height}
            mime_type: {$file->mimetype}
            duration: null
            EOD;
    }

    public function setMetaData($path, string $data): void
    {
        $path = str_replace(['.meta/', '.yaml'], '', $path);

        if (!$file = PixxioFile::find($path)) {
            throw FileException::notFound($path);
        }

        $incomingMetaData = Yaml::parse($data)['data'] ?? [];
        $currentMetaData = Yaml::parse(self::getMetaData($path))['data'];

        if ($incomingMetaData === $currentMetaData) {
            return;
        }

        if ($file->alternative_text !== $incomingMetaData['alt'] ?? '' || $file->copyright !== $incomingMetaData['copyright'] ?? '') {
            self::updateMetaDataOnPixxio($file, $incomingMetaData);
        }

        $file->update([
            'alternative_text' => $incomingMetaData['alt'] ?? null,
            'copyright' => $incomingMetaData['copyright'] ?? null,
            'focus' => $incomingMetaData['focus'] ?? null,
        ]);
    }

    public function listDirectory(): array
    {
        $response = Http::pixxio()
            ->get('/categories', [
                'options' => json_encode([
                    'type' => 'createEditCategories',
                ]),
            ]);

        if ($response->json()['success'] !== 'true') {
            return [];
        }

        return $response->json()['categories'];
    }

    public function listFiles(int $page): array
    {
        $options = array_merge(
            ['pagination' => "500-{$page}"],
            [
                'formatType' => self::FORMAT_TYPE,
                'showVersions' => self::SHOW_VERSIONS,
                'fields' => self::FIELDS,
            ]
        );

        $response = Http::pixxio()
            ->get('/files', [
                'options' => json_encode($options),
            ]);

        if ($response->json()['success'] !== 'true') {
            return [];
        }

        $availablePages = $response->json()['quantity'] / 500;
        $hasMore = $page < $availablePages;
        $nextPage = $hasMore ? $page + 1 : null;

        return [
            'files' => $response->json()['files'],
            'count' => 500,
            'current_page' => $page,
            'next_page' => $nextPage,
            'has_more' => $hasMore,
        ];
    }

    public function getFile($path): ?array
    {
        $segments = explode('/', $path);
        $fileName = end($segments);

        $options = array_merge([
            'pagination' => '1-1',
            'fileName' => $fileName,
        ], [
            'formatType' => self::FORMAT_TYPE,
            'showVersions' => self::SHOW_VERSIONS,
            'fields' => self::FIELDS,
        ]);

        $response = Http::pixxio()
            ->get('/files', [
                'options' => json_encode($options),
            ]);

        if (!$response->successful()
            || $response->json()['success'] !== 'true'
            || empty($response->json()['files'])
        ) {
            return null;
        }

        return $response->json()['files'][0];
    }

    /**
     * Requests all files that have been uploaded today.
     */
    public function getNewFiles(): array
    {
        $options = array_merge([
            'pagination' => '500-1',
            'uploadDateMin' => today()->format('Y-m-d'),
        ], [
            'formatType' => self::FORMAT_TYPE,
            'showVersions' => self::SHOW_VERSIONS,
            'fields' => self::FIELDS,
        ]);

        $response = Http::pixxio()
            ->get('/files', [
                'options' => json_encode($options),
            ]);

        if (!$response->successful() || $response->json()['success'] !== 'true') {
            throw new Exception($response->json()['message']);
        }

        return $response->json()['files'];
    }

    private function updateMetaDataOnPixxio(PixxioFile $file, array $data): void
    {
        $response = Http::pixxio()
            ->asForm()
            ->put("/files/{$file->pixxio_id}", [
                'options' => json_encode([
                    'dynamicMetadata' => [
                        'Alternativetext' => $data['alt'] ?? '',
                        'CopyrightNotice' => $data['copyright'] ?? '',
                    ]
                ])
            ]);

        if ($response->json()['success'] !== 'true') {
            throw new Exception($response->json()['message']);
        }
    }

    private function streamContext()
    {
        return stream_context_create([
            'ssl' => [
                'verify_peer' => $this->verifySSLCertificate,
                'verify_peer_name' => $this->verifySSLCertificate,
            ],
        ]);
    }
}
