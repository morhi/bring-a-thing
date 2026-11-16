<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class PollsFiltersData extends Data
{
    public function __construct(
        public bool $unanswered,
        public bool $mine,
        public bool $all,
    ) {}
}
