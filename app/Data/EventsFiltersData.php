<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class EventsFiltersData extends Data
{
    public function __construct(
        public bool $mine,
        public bool $all,
    ) {}
}
