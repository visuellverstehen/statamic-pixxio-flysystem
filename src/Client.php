<?php

namespace VV\PixxioFlysystem;

use Exception;
use Illuminate\Support\Facades\Http;
use League\Flysystem\UnableToReadFile;
use Statamic\Facades\YAML;
use VV\PixxioFlysystem\Exceptions\FileException;
use VV\PixxioFlysystem\Models\PixxioDirectory;
use VV\PixxioFlysystem\Models\PixxioFile;
use VV\PixxioFlysystem\Traits\PixxioFileHelper;

class Client
{
    use PixxioFileHelper;

    const RESPONSE_FIELDS = [
        'id',
        'fileName',
        'description',
        'directory',
        'fileSize',
        'width',
        'height',
        'uploadDate',
        'createDate',
        'originalFileURL',
        'metadataFields',
    ];

    protected bool $verifySSLCertificate;

    public function __construct()
    {
        $this->verifySSLCertificate = config('statamic.flysystem-pixxio.verify_ssl_certificate', true);
    }

    public function fileExists(string $path): bool
    {
        return (bool) PixxioFile::find($path);
    }

    public function directoryExists(string $path): bool
    {
        return (bool) PixxioDirectory::find($path);
    }

    public function read(string $path): string
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
        if (! $file = PixxioFile::find($path)) {
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

        if (! $file = PixxioFile::find($path)) {
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

        if (! $file = PixxioFile::find($path)) {
            throw FileException::notFound($path);
        }

        $incomingMetaData = Yaml::parse($data)['data'] ?? [];
        $currentMetaData = Yaml::parse(self::getMetaData($path))['data'];

        if ($incomingMetaData === $currentMetaData || empty($incomingMetaData)) {
            return;
        }

        $file->update([
            'alternative_text' => $incomingMetaData['alt'] ?? null,
            'copyright' => $incomingMetaData['copyright'] ?? null,
            'focus' => $incomingMetaData['focus'] ?? null,
        ]);
    }

    public function listFiles(?string $pageCursor = null): array
    {
        $options = [
            'pageCursor' => $pageCursor,
            'pageSize' => 500,
            'approximateQuantity' => true,
            'showFiles' => true,
            'directoryResponseFields' => json_encode([
                'path',
            ]),
            'sortBy' => 'uploadDate',
            'sortDirection' => 'asc',
            'responseFields' => json_encode(self::RESPONSE_FIELDS),
            'filter' => json_encode([
                'filterType' => 'connectorAnd',
                'filters' => [
                    [
                        'filterType' => 'formatType',
                        'formatType' => 'webimage',
                    ],
                ],
            ]),
        ];

        $response = Http::pixxio()
            ->get('/files', $options);

        if ($response->json()['success'] !== true) {
            throw new Exception("Error while trying to fetch all files from Pixx.io: {$response->body()}");
        }

        $pageCursor = $response->json()['cursor'] ?? null;

        return [
            'files' => $response->json()['files'],
            'pageCursor' => $pageCursor,
            'has_more' => ! is_null($pageCursor),
        ];
    }

    public function getFile(int $id): ?array
    {
        $options = [
            'directoryResponseFields' => json_encode([
                'path',
            ]),
            'responseFields' => json_encode(self::RESPONSE_FIELDS),
        ];

        $response = Http::pixxio()
            ->get("/files/{$id}", $options);

        if ($response->json()['success'] !== true) {
            return null;
        }

        return $response->json()['file'];
    }

    /**
     * Requests all files that have been uploaded today.
     */
    public function getNewFiles(?string $pageCursor = null): array
    {
        $options = [
            'pageCursor' => $pageCursor,
            'pageSize' => 500,
            'approximateQuantity' => true,
            'showFiles' => true,
            'directoryResponseFields' => json_encode([
                'path',
            ]),
            'filter' => json_encode([
                    'filterType' => 'connectorAnd',
                    'filters' => [
                        [
                            'filterType' => 'uploadDate',
                            'dateMin' => today()->format('Y-m-d H:i:s'),
                        ],
                        [
                            'filterType' => 'formatType',
                            'formatType' => 'webimage',
                        ]
                    ],
                ]),
            'sortBy' => 'uploadDate',
            'sortDirection' => 'asc',
            'responseFields' => json_encode(self::RESPONSE_FIELDS),
        ];

        $response = Http::pixxio()
            ->get('/files', $options);

         if ($response->json()['success'] !== true) {
            throw new Exception("Error while trying to fetch new files from Pixx.io: {$response->body()}");
        }

        $pageCursor = $response->json()['cursor'] ?? null;

        return [
            'files' => $response->json()['files'],
            'pageCursor' => $pageCursor,
            'has_more' => ! is_null($pageCursor),
        ];
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
