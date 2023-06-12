<?php

namespace VV\PixxioFlysystem\Sync;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use VV\PixxioFlysystem\Client;
use VV\PixxioFlysystem\Models\PixxioFile;
use VV\PixxioFlysystem\Traits\PixxioFileHelper;

/**
 * todo: sync new directories too.
 */
class SyncNewFilesOnly
{
    use PixxioFileHelper;

    protected Command $command;
    protected Client $client;
    protected array $config;

    public function __construct($command)
    {
        $this->command = $command;
        $this->client = new Client();
    }

    public function handle(): void
    {
        try {
            $start = now();
            $files = $this->client->getNewFiles();

            $imported = collect();

            // Keep only files that have been uploaded in the past five minutes
            // and have not been saved to database yet.
            $filesToCreate = collect($files)
                ->filter(function ($fileData) {
                    $fiveMinutesAgo = today()->subMinutes(5000);
                    $uploadDate = Carbon::createFromTimeString($fileData['uploadDate']);

                    return $uploadDate->isAfter($fiveMinutesAgo)
                        && !PixxioFile::find(self::getRelativePath($fileData));
                });

            // Save files to database.
            $filesToCreate->each(function ($fileData) use ($imported) {
                if ($file = self::createPixxioFile($fileData)) {
                    $imported->push($file);
                }
            });

            if ($imported->count() > 0) {
                $time = $start->diffInSeconds(now());
                $this->command->info("Success! We found and imported {$imported->count()} newly uploaded files in {$time} seconds.");

                return;
            }

            $this->command->info('We could not find any newly uploaded files.');
        } catch (\Exception $exception) {
            $this->command->error($exception->getMessage());
        }


    }
}