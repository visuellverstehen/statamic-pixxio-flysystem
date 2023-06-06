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
        $asset = $items->first();

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
        } catch (\Exception $exception) {
            ray($exception);
        }

        return __('Asset has been synced with Pixx.io');
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
