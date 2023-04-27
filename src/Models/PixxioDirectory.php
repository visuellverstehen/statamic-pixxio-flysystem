<?php

namespace VV\PixxioFlysystem\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PixxioDirectory extends Model
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
        'relative_path',
        'updated_at',
    ];

    public function scopeUpdatedAtOlderThan(Builder $query, int $interval): void
    {
        $query->where('updated_at', '<=', now()->subMinutes($interval)->toDateTimeString());
    }
}
