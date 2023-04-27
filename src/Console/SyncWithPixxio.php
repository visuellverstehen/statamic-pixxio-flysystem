<?php

namespace VV\PixxioFlysystem\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use VV\PixxioFlysystem\Client;
use VV\PixxioFlysystem\Models\PixxioDirectory;
use VV\PixxioFlysystem\Models\PixxioFile;

class SyncWithPixxio extends Command
{
    protected $signature = 'pixxio:sync';
    protected $description = 'Sync database with Pixxio';
    protected Client $client;
    protected array $config;

    public function handle()
    {
        $start = now();
        $this->client = new Client();
        $this->config = config('statamic.flysystem-pixxio');

        self::updatePixxioDirectoriesTable();
        self::updatePixxioFilesTable();

        $time = $start->diffInSeconds(now());
        $this->info("Success! Files and directories have been synced in {$time} seconds.");
    }

    private function updatePixxioDirectoriesTable(): void
    {
        $this->comment('1) Sync directories');

        $directories = $this->client->listDirectory();

        $progressBar = $this->output->createProgressBar(count($directories));
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

    private function updatePixxioFilesTable()
    {
        $this->comment('2) Sync files');

        foreach (self::getAllFiles() as &$files) {
            $progressBar = $this->output->createProgressBar(count($files));

            $progressBar->start();

            foreach ($files as $file) {
                $directory = $file['category'] ?? '';
                $relativePath = "{$directory}/{$file['originalFilename']}";

                if (self::shouldBeExcluded($relativePath)) {
                    continue;
                }

                PixxioFile::updateOrCreate(
                    [
                        'relative_path' => $relativePath,
                    ],
                    [
                        'pixxio_id' => (int) $file['id'],
                        'relative_path' => $relativePath,
                        'absolute_path' => $file['imagePath'],
                        'filesize' => $file['fileSize'],
                        'last_modified' => $file['uploadDate'] ?? null,
                        'alternative_text' => 'Juuuhuuu alt text!',
                        'updated_at' => now(),
                    ]
                );

                $progressBar->advance();
            }
            $progressBar->finish();
            $this->newLine(2);
        }

        self::deleteNonExistingFiles();
    }

    private function deleteNonExistingDirectories(): void
    {
        $directoriesToBeDeleted = PixxioDirectory::query()->updatedAtOlderThan(5)->get();

        $directoriesToBeDeleted->each(function ($directory) {
            $directory->delete();
        });

        $this->newLine();
        $this->comment("Deleted directories: {$directoriesToBeDeleted->count()}");
    }

    private function deleteNonExistingFiles(): void
    {
        // Right now we assume that the syncronization process doesn't last more than 5 minutes.
        $filesToBeDeleted = PixxioFile::query()->updatedAtOlderThan(5)->get();

        $filesToBeDeleted->each(function ($file) {
            $file->delete();
        });

        $this->comment("Deleted files: {$filesToBeDeleted->count()} ");
    }

    private function &getAllFiles()
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
