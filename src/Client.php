<?php

namespace VV\PixxioFlysystem;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use Statamic\Facades\YAML;
use VV\PixxioFlysystem\Exceptions\FileException;
use VV\PixxioFlysystem\Models\PixxioDirectory;
use VV\PixxioFlysystem\Models\PixxioFile;

class Client
{
    protected string $refreshToken;
    protected string $apiKey;
    protected string $endpoint;
    protected bool $verifySSLCertificate;

    public function __construct()
    {
        $this->apiKey = config('filesystems.disks.pixxio.api_key', '');
        $this->refreshToken = config('filesystems.disks.pixxio.refresh_token', '');
        $this->endpoint = config('filesystems.disks.pixxio.endpoint', '');
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
            ->post("{$this->endpoint}/categories", [
                'accessToken' => self::getAccessToken(),
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
            ->withHeaders([
                'accessToken' => self::getAccessToken(),
            ])
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
            ->withHeaders([
                'accessToken' => self::getAccessToken(),
            ])
            ->delete("/categories/?options={$urlEncodedOptions}");

        if ($response->json()['success'] !== 'true') {
            throw new Exception($response->json()['message']);
        }

        $directory->delete();
    }

    public function upload($path, $contents): bool
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
                'accessToken' => self::getAccessToken(),
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
                'accessToken' => self::getAccessToken(),
            ]);

        $file = $fileResponse->json();

        $directory = $file['category'] ?? '';
        $relativePath = "{$directory}/{$file['originalFilename']}";

        PixxioFile::create([
            'pixxio_id' => $file['id'],
            'relative_path' => $relativePath,
            'absolute_path' => $file['imagePath'],
            'filesize' => $file['fileSize'],
            'last_modified' => $file['uploadDate'] ?? null,
        ]);

        return true;
    }

    public function read($path): string
    {
        error_clear_last();
        $contents = @file_get_contents(
            $path,
            false,
            self::streamingContext(),
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
        $contents = @fopen($file->absolute_path, 'rb', false, self::streamingContext());

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
                'accessToken' => self::getAccessToken(),
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
        $response = Http::pixxio()
            ->get('/files', [
                'accessToken' => self::getAccessToken(),
                'options' => json_encode([
                    'pagination' => "500-{$page}",
                    'formatType' => 'webimage',
                    'fields' => [
                        'id', 'category', 'originalPath',
                        'imagePath', 'links',
                        'originalFilename', 'formatType',
                        'fileSize', 'fileType', 'description',
                        'uploadDate', 'createDate', 'imageHeight',
                        'imageWidth', 'subject', 'dynamicMetadata',
                    ],
                ]),
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

    /*
     * Access Tokens are valid for 30 minutes. But right now we only store the current token for 5 minutes and make a new request.
     */
    private function getAccessToken(): ?string
    {
        if ($existingToken = Cache::get('pixxio-access')) {
            return Crypt::decryptString($existingToken);
        }

        // Request token.
        $response = Http::pixxio()
            ->withBody(json_encode([
                'refreshToken' => $this->refreshToken,
                'apiKey' => $this->apiKey,
            ]))
            ->post("{$this->endpoint}/accessToken");

        if (!$response->successful()) {
            throw new Exception($response->json()['message']);
        }

        if (empty($response->json())) {
            throw new Exception('Response is empty. Please check your pixx.io credentials.');
        }

        if (!array_key_exists('accessToken', $response->json())) {
            return null;
        }

        $accessToken = $response->json()['accessToken'];

        Cache::put('pixxio-access', Crypt::encryptString($accessToken), 300);

        return $accessToken;
    }

    private function streamingContext()
    {
        return stream_context_create([
            'ssl' => [
                'verify_peer' => $this->verifySSLCertificate,
                'verify_peer_name' => $this->verifySSLCertificate,
            ],
        ]);
    }

    private function updateMetaDataOnPixxio(PixxioFile $file, array $data): void
    {
        $response = Http::pixxio()
            ->asForm()
            ->put("/files/{$file->pixxio_id}", [
                'accessToken' => self::getAccessToken(),
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
}
