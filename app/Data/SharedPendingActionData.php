<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SharedPendingActionData extends Data
{
    /**
     * @param  string  $kind  "claim" or "add"
     */
    public function __construct(
        public string $kind,
        public string $name,
    ) {}
}
