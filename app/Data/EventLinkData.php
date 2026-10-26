<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class EventLinkData extends Data
{
    public function __construct(
        public string $slug,
        public string $title,
    ) {}
}
