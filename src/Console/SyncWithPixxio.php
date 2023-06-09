<?php

namespace VV\PixxioFlysystem\Console;

use Illuminate\Console\Command;
use VV\PixxioFlysystem\Sync\SyncNewFilesOnly;
use VV\PixxioFlysystem\Sync\SyncAllFilesAndDirectories;

class SyncWithPixxio extends Command
{
    protected $signature = 'pixxio:sync {--new}';
    protected $description = 'Sync database with Pixxio';

    public function handle()
    {
        if ($this->option('new')) {
            (new SyncNewFilesOnly($this))->handle();

            return;
        }

        (new SyncAllFilesAndDirectories($this))->handle();
    }
}
