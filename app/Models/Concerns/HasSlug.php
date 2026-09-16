<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Give a model an opaque, unique slug used as its route key instead of its incrementing id.
 */
trait HasSlug
{
    /**
     * Boot the trait, assigning a random slug to every new model.
     */
    protected static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            $model->slug ??= Str::random(32);
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
