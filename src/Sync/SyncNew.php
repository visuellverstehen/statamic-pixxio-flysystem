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
            $imported = collect();

            foreach (self::getAllNewFiles() as &$files) {
                $existing = PixxioFile::query()
                    ->whereIn('relative_path', array_map(fn ($fileData) => self::getRelativePath($fileData), $files))
                    ->orWhereIn('pixxio_id', array_column($files, 'id'))
                    ->get(['relative_path', 'pixxio_id']);

                $existingPaths = $existing->pluck('relative_path')->flip()->all();
                $existingIds = $existing->pluck('pixxio_id')->flip()->all();

                foreach ($files as $fileData) {
                    $path = self::getRelativePath($fileData);

                    if (isset($existingPaths[$path]) || isset($existingIds[$fileData['id']])) {
                        continue;
                    }

                    $imported->push(self::createPixxioFile($fileData));
                    $existingPaths[$path] = true;
                    $existingIds[$fileData['id']] = true;
                }
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
