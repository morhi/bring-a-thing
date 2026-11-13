<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ListsFiltersData extends Data
{
    public function __construct(
        public bool $mine,
        public bool $tasks,
        public bool $all,
    ) {}
}
