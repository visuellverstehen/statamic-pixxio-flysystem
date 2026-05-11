<?php

namespace VV\PixxioFlysystem\Sync;

use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use VV\PixxioFlysystem\Client;
use VV\PixxioFlysystem\Models\PixxioDirectory;
use VV\PixxioFlysystem\Models\PixxioFile;
use VV\PixxioFlysystem\Traits\PixxioFileHelper;
use VV\PixxioFlysystem\Utilities\PixxioFileMapper;

class SyncAll
{
    use PixxioFileHelper;

    protected Client $client;
    protected array $config;
    protected Command $command;

    public function __construct($command)
    {
        $this->command = $command;
        $this->client = new Client();
        $this->config = config('statamic.flysystem-pixxio');
    }

    public function handle(): void
    {
        $start = now();

        self::sync();

        $time = $start->diffInSeconds(now());
        $this->command->info("Success! Files and directories have been synced in {$time} seconds.");
    }

    private function sync(): void
    {
        $this->command->comment('Synchronizing all files');

        foreach (self::getAllFiles() as &$files) {
            $fileRows = [];
            $directoryRows = [];
            $seenDirectories = [];

            $progressBar = $this->command->getOutput()->createProgressBar(count($files));

            $progressBar->start();

            foreach ($files as $file) {
                $relativePath = self::getRelativePath($file);

                if (self::shouldBeExcluded($relativePath)) {
                    $progressBar->advance();

                    continue;
                }

                $incomingFileData = (new PixxioFileMapper($file))->toArray();
                $fileRows[] = $incomingFileData;

                foreach ($this->directoriesForPath($incomingFileData['relative_path']) as $dirPath) {
                    if (isset($seenDirectories[$dirPath])) {
                        continue;
                    }

                    $seenDirectories[$dirPath] = true;
                    $directoryRows[] = [
                        'relative_path' => $dirPath,
                        'updated_at' => now(),
                    ];
                }

                $progressBar->advance();
            }

            $this->upsertDirectories($directoryRows);
            $this->upsertFiles($fileRows);

            $progressBar->finish();
            $this->command->newLine(2);
        }

        self::deleteNonExistingFiles();
        self::deleteNonExistingDirectories();
    }

    private function directoriesForPath(string $filePath): array
    {
        $directoryPath = trim(dirname($filePath), '/');

        if ($directoryPath === '' || $this->shouldBeExcluded('/' . $directoryPath)) {
            return [];
        }

        $segments = explode('/', $directoryPath);
        $current = '';
        $dirs = [];

        foreach ($segments as $segment) {
            $current .= '/' . $segment;

            if ($this->shouldBeExcluded($current)) {
                continue;
            }

            $dirs[] = $current;
        }

        return $dirs;
    }

    private function upsertDirectories(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        PixxioDirectory::upsert(
            $rows,
            ['relative_path'],
            ['updated_at'],
        );
    }

    private function upsertFiles(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        PixxioFile::upsert(
            $rows,
            ['relative_path'],
            [
                'pixxio_id',
                'absolute_path',
                'filesize',
                'width',
                'height',
                'mimetype',
                'last_modified',
                'alternative_text',
                'copyright',
                'description',
                'updated_at',
            ],
        );
    }

    private function deleteNonExistingDirectories(): void
    {
        $twentyFourHours = 60 * 24;

        $directoriesToBeDeleted = PixxioDirectory::query()->updatedAtOlderThan($twentyFourHours)->get();

        $directoriesToBeDeleted->each(function ($directory) {
            $directory->delete();
        });

        $this->command->newLine();
        $this->command->comment("Deleted directories: {$directoriesToBeDeleted->count()}");
    }

    private function deleteNonExistingFiles(): void
    {
        $twentyFourHours = 60 * 24;

        $filesToBeDeleted = PixxioFile::query()->updatedAtOlderThan($twentyFourHours)->get();

        $filesToBeDeleted->each(function ($file) {
            $file->delete();
        });

        $this->command->comment("Deleted files: {$filesToBeDeleted->count()} ");
    }

    private function &getAllFiles(): Generator
    {
        $hasMore = true;
        $pageCursor = null;

        while ($hasMore) {
            $result = $this->client->listFiles($pageCursor);

            $hasMore = $result['has_more'];
            $pageCursor = $result['pageCursor'];

            yield $result['files'];
        }
    }

    private function shouldBeExcluded(string $path): bool
    {
        if (Str::endsWith($path, '/.meta')) {
            return true;
        }

        foreach ($this->config['exclude']['directories'] as $excludedPath) {
            if (Str::startsWith($path, $excludedPath)) {
                return true;
            }
        }

        return false;
    }
}
