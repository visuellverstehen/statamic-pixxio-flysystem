<?php

namespace VV\PixxioFlysystem\Sync;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use VV\PixxioFlysystem\Client;
use VV\PixxioFlysystem\Models\PixxioDirectory;
use VV\PixxioFlysystem\Models\PixxioFile;
use VV\PixxioFlysystem\Traits\PixxioFileHelper;
use VV\PixxioFlysystem\Utilities\PixxioFileMapper;

class SyncAllFilesAndDirectories
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

        self::syncDirectories();
        self::syncFiles();

        $time = $start->diffInSeconds(now());
        $this->command->info("Success! Files and directories have been synced in {$time} seconds.");
    }

    private function syncDirectories(): void
    {
        $this->command->comment('Synchronizing all directories');

        $directories = $this->client->listDirectory();

        $progressBar = $this->command->getOutput()->createProgressBar(count($directories));
        $progressBar->start();

        foreach ($directories as $directory) {
            if (self::shouldBeExcluded($directory)) {
                continue;
            }

            PixxioDirectory::updateOrCreate(
                ['relative_path' => $directory],
                ['updated_at' => now()]
            );

            $progressBar->advance();
        }

        $progressBar->finish();

        self::deleteNonExistingDirectories();
    }

    private function syncFiles(): void
    {
        $this->command->comment('Synchronizing all files');

        foreach (self::getAllFiles() as &$files) {
            $progressBar = $this->command->getOutput()->createProgressBar(count($files));

            $progressBar->start();

            foreach ($files as $file) {
                $relativePath = self::getRelativePath($file);

                if (self::shouldBeExcluded($relativePath)) {
                    continue;
                }

                $pixxioFile = PixxioFile::where('relative_path', $relativePath)
                    ->where('pixxio_id', $file['id'])
                    ->first();
                
                if ($pixxioFile) {
                    $pixxioFile->update((new PixxioFileMapper($file))->toArray());
                    $progressBar->advance();
                
                    continue;
                }
                
                $result = PixxioFile::where('relative_path', $relativePath)
                    ->orWhere('pixxio_id', $file['id'])
                    ->get();
                
                if ($result->isEmpty()) {
                    PixxioFile::create((new PixxioFileMapper($file))->toArray());
                }
                
                $progressBar->advance();
            }
            $progressBar->finish();
            $this->command->newLine(2);
        }

        self::deleteNonExistingFiles();
    }

    private function deleteNonExistingDirectories(): void
    {
        $directoriesToBeDeleted = PixxioDirectory::query()->updatedAtOlderThan(5)->get();

        $directoriesToBeDeleted->each(function ($directory) {
            $directory->delete();
        });

        $this->command->newLine();
        $this->command->comment("Deleted directories: {$directoriesToBeDeleted->count()}");
    }

    private function deleteNonExistingFiles(): void
    {
        // Right now we assume that the synchronization process doesn't last more than 5 minutes.
        $filesToBeDeleted = PixxioFile::query()->updatedAtOlderThan(5)->get();

        $filesToBeDeleted->each(function ($file) {
            $file->delete();
        });

        $this->command->comment("Deleted files: {$filesToBeDeleted->count()} ");
    }

    private function &getAllFiles(): \Generator
    {
        $hasMore = true;
        $page = 1;

        while ($hasMore) {
            $result = $this->client->listFiles($page);

            $hasMore = $result['has_more'];
            $page = $result['next_page'];

            yield $result['files'];
        }
    }

    private function shouldBeExcluded($path): bool
    {
        foreach ($this->config['exclude']['directories'] as $excludedPath) {
            if (Str::startsWith($path, $excludedPath)) {
                return true;
            }
        }

        return false;
    }
}