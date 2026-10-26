<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SharedFlashData extends Data
{
    public function __construct(
        public ?string $success,
        public ?string $error,
    ) {}
}
