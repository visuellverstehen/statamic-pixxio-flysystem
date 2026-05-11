<?php

namespace VV\PixxioFlysystem\Sync;

use Exception;
use Generator;
use Illuminate\Console\Command;
use VV\PixxioFlysystem\Client;
use VV\PixxioFlysystem\Models\PixxioFile;
use VV\PixxioFlysystem\Traits\PixxioFileHelper;

class SyncNew
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
            $files = $this->client->getNewFiles();
            
            $imported = collect();

            foreach (self::getAllNewFiles() as &$files) {

                // Keep only files that haven't been saved to database yet.
                $filesToCreate = collect($files)
                    ->filter(function ($fileData) {

                        return ! PixxioFile::where('relative_path', self::getRelativePath($fileData))
                            ->orWhere('pixxio_id', $fileData['id'])
                            ->first();
                    });

                // Save files to database.
                $filesToCreate->each(function ($fileData) use ($imported) {
                    if ($file = self::createPixxioFile($fileData)) {

                        $imported->push($file);
                    }
                });
            }

            $this->command->info("Everything is up to date. {$imported->count()} new files have been found.");
        } catch (Exception $exception) {
            $this->command->error($exception->getMessage());
        }

    }

    private function &getAllNewFiles(): Generator
    {
        $hasMore = true;
        $pageCursor = null;

        while ($hasMore) {
            $result = $this->client->getNewFiles($pageCursor);

            $hasMore = $result['has_more'];
            $pageCursor = $result['pageCursor'];

            yield $result['files'];
        }
    }
}
