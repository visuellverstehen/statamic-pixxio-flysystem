<?php

namespace VV\PixxioFlysystem\Console;

use Illuminate\Console\Command;
use VV\PixxioFlysystem\Sync\SyncAll;
use VV\PixxioFlysystem\Sync\SyncNew;

class SyncWithPixxio extends Command
{
    protected $signature = 'pixxio:sync {--new}';
    protected $description = 'Sync database with Pixxio';

    public function handle()
    {
        if ($this->option('new')) {
            (new SyncNew($this))->handle();

            return;
        }

        (new SyncAll($this))->handle();
    }
}
