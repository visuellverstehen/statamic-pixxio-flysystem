<?php

namespace VV\PixxioFlysystem\Actions;

use GuzzleHttp\Psr7\MimeType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Statamic\Actions\Action;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\YAML;
use VV\PixxioFlysystem\Client;
use VV\PixxioFlysystem\Models\PixxioFile;

class SyncAssetWithPixxioAction extends Action
{
    public function run($items, $values)
    {
        $failed = collect();
        $succeeded = collect();

        $items->each(function ($asset) use ($failed, $succeeded) {
            $path = Str::start($asset->path, '/');
            $file = PixxioFile::find($path);

            try {
                $client = new Client();
                $incomingData = $client->getFile($path);

                $file->update([
                    'absolute_path' => $incomingData['imagePath'],
                    'filesize' => $incomingData['fileSize'],
                    'width' => $incomingData['imageWidth'],
                    'height' => $incomingData['imageHeight'],
                    'last_modified' => $incomingData['uploadDate'] ?? now()->format('Y-m-d H:i:s'),
                    'alternative_text' => $incomingData['dynamicMetadata']['Alternativetext'],
                    'copyright' => $incomingData['dynamicMetadata']['CopyrightNotice'],
                    'updated_at' => now(),
                ]);

                Cache::forget($asset->metaCacheKey());

                $succeeded->push($file);
            } catch (\Exception $exception) {
                $failed->push($file);
            }

        });

        // todo: refactor
        if ($failed->isNotEmpty()) {
            if ($failed->count() < 2) {
                // todo: translate
                throw new \Exception("{$failed->first()->filename} failed to syncronize.");
            }

            // todo: translate
            throw new \Exception("{$failed->count()} files failed to syncronize.");
        } else {

            if ($succeeded->count() < 2) {

                // todo: translate
                return "{$succeeded->first()->filename} has been syncronized.";
            }

            // todo: translate
            return $succeeded->count() . ' files were syncronized.';
        }
    }

    public static function title()
    {
        return __('Sync with Pixx.io');
    }

    public function visibleTo($item)
    {
        return $item instanceof Asset && self::usesPixxioDriver();
    }

    private function usesPixxioDriver(): bool
    {
        if (!array_key_exists('container', $this->context)) {
            return false;
        }

        return $this->context['container'] === 'pixxio';
    }
}
