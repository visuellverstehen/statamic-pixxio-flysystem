<?php

namespace VV\PixxioFlysystem;

use Exception;
use GuzzleHttp\Psr7\MimeType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use VV\PixxioFlysystem\Models\PixxioFile;

class Client
{
    protected string $refreshToken;
    protected string $apiKey;
    protected string $endpoint;

    public function __construct()
    {
        $this->apiKey = config('filesystems.disks.pixxio.api_key');
        $this->refreshToken = config('filesystems.disks.pixxio.refresh_token');
        $this->endpoint = config('filesystems.disks.pixxio.endpoint');
    }

    /*
     * Docs: https://bilder.fh-dortmund.de/cgi-bin/api/pixxio-api.pl/documentation/files/get
     */
    public function fileExists(string $path): bool
    {
        return ! empty(self::findFileByPath($path));
    }

    /*
     * Docs: https://bilder.fh-dortmund.de/cgi-bin/api/pixxio-api.pl/documentation/categories/get
     */
    public function directoryExists(string $path): bool
    {
        try {
            $response = Http::withoutVerifying()
                ->get("{$this->endpoint}/json/categories/exists", [
                    'accessToken' => self::getAccessToken(),
                    'options' => json_encode([
                        'destinationCategory' => $path,
                    ]),
                ]);

            // The api returns booleans as string.
            return $response->json()['exists'] === 'true';
        } catch (Exception $e) {
            // todo:
            return false;
        }
    }

    /*
     * https://bilder.fh-dortmund.de/cgi-bin/api/pixxio-api.pl/documentation/categories
     */
    public function createDirectory($path): bool
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
            if (! self::directoryExists($rootDirectory)) {
                // Do not try to create directory.
                return false;
            }
        }

        // Define directory name
        $directoryName = $slashCount > 0
            ? Str::substr($path, $pos + 1, $length)
            : Str::substr($path, 0, $length);

        $response = Http::withoutVerifying()
            ->post("{$this->endpoint}/json/categories", [
                'accessToken' => self::getAccessToken(),
                'options' => json_encode([
                    'categoryName' => $directoryName,
                    'rootCategory' => $rootDirectory,
                ]),
            ]);

        return $response->json()['success'] === 'true';
    }

    public function deleteFile($path): void
    {
        // todo: why is this not working?
        if (! $file = PixxioFile::find($path)) {
            throw UnableToDeleteFile::atLocation($path, 'File could not be found in database');
        }

        $response = Http::withoutVerifying()
            ->delete("{$this->endpoint}/json/files/{$file->id}", [
                'accessToken' => self::getAccessToken(),
            ]);

        if ($response->json()['success'] !== 'true') {
            throw UnableToDeleteFile::atLocation($path, $response->json()['message']);
        }
    }

    public function deleteDirectory($path): void
    {
        // todo: why is this not working?
        $response = Http::withoutVerifying()
            ->delete("{$this->endpoint}/json/categories", [
                'accessToken' => self::getAccessToken(),
                'options' => json_encode([
                    'destinationCategory' => $path,
                ]),
            ]);

        if ($response->json()['success'] !== 'true') {
            throw UnableToDeleteDirectory::atLocation($path, $response->json()['message']);
        }
    }

    /*
     * Docs:
     * https://bilder.fh-dortmund.de/cgi-bin/api/pixxio-api.pl/documentation/files/post
     */
    public function upload($path, $contents): bool
    {
        $fileContents = stream_get_contents($contents);

        $lastSlash = strrpos($path, '/');
        $strLength = strlen($path);

        $directory = substr($path, 0, $lastSlash);
        $fileName = trim(substr($path, $lastSlash, $strLength), '/');

        // The file upload was successful. You find the file in your pixxio-system, when the conversion is finished.
        // To receive the fileId and therefore convert the file immediatly, you need to set forceConversion = "true".
        $response = Http::withoutVerifying()
            ->attach('file', $fileContents, $fileName)
            ->post("{$this->endpoint}/json/files", [
                'accessToken' => self::getAccessToken(),
                'options' => json_encode([
                    'category' => $directory,
                    'forceConversion' => 'true',
                    'description' => 'uploaded by statamic',
                ]),
            ]);

        if ($response->json()['success'] !== 'true') {
            return false;
        }

        // add new file to database
        $fileId = $response->json()['fileId'];

        $fileResponse = Http::withoutVerifying()
            ->get("{$this->endpoint}/json/files/{$fileId}", [
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
            stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ])
        );

        if ($contents === false) {
            throw UnableToReadFile::fromLocation($path, error_get_last()['message'] ?? '');
        }

        return $contents;
    }

    public function readStream(string $path)
    {
        if (! $file = PixxioFile::find($path)) {
            // todo: throw exception. Could not find file.
        }

        error_clear_last();

        return fopen($file->absolute_path, 'rb', false, stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]));
    }

    public function mimeType(string $path)
    {
        return MimeType::fromFilename($path);
    }

    public function fileSize(string $path): int
    {
        if (! $file = self::findFileByPath($path)) {
            return 0;
        }

        return $file['fileSize'] ?? 0;
    }

    public function lastModified(string $path)// timestamp
    {
        if (! $file = self::findFileByPath($path)) {
            return null;
        }

        return Carbon::createFromTimeString($file['uploadDate'])->timestamp;
    }

    /*
     * https://bilder.fh-dortmund.de/cgi-bin/api/pixxio-api.pl/documentation/categories/get
     */
    public function listDirectory(): array
    {
        $response = Http::withoutVerifying()
            ->get("{$this->endpoint}/json/categories", [
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
        $response = Http::withoutVerifying()
            ->get("{$this->endpoint}/json/files", [
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
                        'imageWidth', 'subject',
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
     *
     * todo: what if we have an invalid accessToken?
     */
    private function getAccessToken(): ?string
    {
        if ($existingToken = Cache::get('pixxio-access')) {
            return Crypt::decryptString($existingToken);
        }

        // Request token.
        $response = Http::withoutVerifying()
            ->withBody(json_encode([
                'refreshToken' => $this->refreshToken,
                'apiKey' => $this->apiKey,
            ]), 'application/json')
            ->post("{$this->endpoint}/json/accessToken");

        if (! $response->successful()) {
            // todo: throw custom exception.
            return null;
        }

        if (! array_key_exists('accessToken', $response->json())) {
            return null;
        }

        $accessToken = $response->json()['accessToken'];
        // todo: check if 'fehleranfällig'.
        Cache::put('pixxio-access', Crypt::encryptString($accessToken), 300);

        return $accessToken;
    }

    private function findFileByPath(string $path): ?array
    {
        $length = strlen($path);
        $pos = strripos($path, '/');

        $directory = Str::substr($path, 0, $pos);
        $fileName = Str::substr($path, $pos, $length);

        try {
            $response = Http::withoutVerifying()
                ->get("{$this->endpoint}/json/files", [
                    'accessToken' => self::getAccessToken(),
                    'options' => json_encode([
                        'fileName' => trim($fileName, '/'),
                        'category' => $directory, // path to file??
                        'pagination' => '1-1',
                    ]),
                ]);

            if ($response->json()['success'] !== 'true') {
                return null;
            }

            if (empty($response->json()['files'])) {
                return null;
            }

            return $response->json()['files'][0];
        } catch (Exception $e) {
            return null;
        }
    }
}
