<?php

namespace VV\PixxioFlysystem\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\AssetContainer;
use Stringy\StaticStringy;

class PixxioFile extends Model
{
    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'relative_path';

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    protected $fillable = [
        'pixxio_id', 'relative_path', 'absolute_path',
        'alternative_text', 'copyright',
        'visibility', 'last_modified', 'updated_at',
        'filesize', 'mimetype', 'height', 'width',
        'focus', 'description',
    ];

    protected static function booted(): void
    {
        static::created(function (PixxioFile $pixxioFile) {
            if (!$container = AssetContainer::findByHandle('pixxio')) {
                return;
            }

            $sanitizedPath = StaticStringy::removeLeft($pixxioFile->relative_path, '/');

            try {
                $asset = $container->makeAsset($sanitizedPath);
                $asset->save();
            } catch (\Exception $exception) {
                Log::error('Asset could not be saved: ' . $exception->getMessage(), [
                    'path' => $sanitizedPath,
                    'asset_container' => 'pixxio',
                ]);
            }
        });
    }

    public function getFilenameAttribute(): string
    {
        $segments = explode('/', $this->relative_path);

        return end($segments);
    }

    public function scopeUpdatedAtOlderThan(Builder $query, int $interval): void
    {
        $query->where('updated_at', '<=', now()->subMinutes($interval)->toDateTimeString());
    }

    protected function lastModified(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => is_null($attributes['last_modified']) ? null :Carbon::createFromTimeString($attributes['last_modified'])->timestamp,
        );
    }
}
