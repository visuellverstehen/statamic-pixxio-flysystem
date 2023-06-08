<?php

namespace VV\PixxioFlysystem\Console;

use Illuminate\Console\Command;
use VV\PixxioFlysystem\Client;

class NewSync extends Command
{
    protected $signature = 'pixxio:new-sync';
    protected $description = 'Command description';

    public function handle()
    {
        try {
            $client = new Client();
            $count = $client->importNewFiles();

            if ($count > 0) {
                $this->info("Success! We found and imported {$count} newly uploaded files.");

                return;
            }

            $this->info('We could not find any newly uploaded files.');
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }

    }
}
