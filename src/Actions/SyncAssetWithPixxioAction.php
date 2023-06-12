<?php

namespace VV\PixxioFlysystem\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Statamic\Actions\Action;
use Statamic\Contracts\Assets\Asset;
use VV\PixxioFlysystem\Client;
use VV\PixxioFlysystem\Models\PixxioFile;
use VV\PixxioFlysystem\Utilities\PixxioFileMapper;

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
                $incomingFileData = (new PixxioFileMapper($client->getFile($path)))->toArray();

                $file->update($incomingFileData);

                $succeeded->push($file);

                Cache::forget($asset->metaCacheKey());
            } catch (\Exception $exception) {
                $failed->push($file);
            }

        });

        if ($failed->isNotEmpty()) {
            $failed->count() > 1
                ? throw new \Exception(__(':count files failed to synchronize.', ['count' => $failed->count()]))
                : throw new \Exception(__(':filename failed to synchronize.', ['filename' => $failed->first()->filename]));
        }

        return $succeeded->count() > 1
            ? __(':count files were synchronized.', ['count' => $succeeded->count()])
            : __(':filename has been synchronized.', ['filename' => $succeeded->first()->filename]);
    }

    public static function title()
    {
        return __('Synchronize with pixx.io');
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
