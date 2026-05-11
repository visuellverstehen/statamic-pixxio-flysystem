<?php

namespace VV\PixxioFlysystem\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\AssetContainer;
use Stringy\StaticStringy;

class CreateAssetFromPixxioFile implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(protected string $relativePath) {}

    public function handle(): void
    {
        if (! $container = AssetContainer::findByHandle('pixxio')) {
            return;
        }

        $sanitizedPath = StaticStringy::removeLeft($this->relativePath, '/');

        try {
            $asset = $container->makeAsset($sanitizedPath);
            $asset->save();
        } catch (Exception $e) {
            Log::error('Asset could not be created from pixxio file: ' . $e->getMessage(), [
                'relative_path' => $this->relativePath,
            ]);
        }
    }
}
