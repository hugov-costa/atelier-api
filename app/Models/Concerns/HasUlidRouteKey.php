<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Gives a model a public, non-enumerable ULID identifier and binds routes to it
 * instead of the auto-increment key, so internal keys never leak and resources
 * cannot be enumerated by guessing sequential ids.
 */
trait HasUlidRouteKey
{
    public static function bootHasUlidRouteKey(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('ulid'))) {
                $model->setAttribute('ulid', (string) Str::ulid());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
