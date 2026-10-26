<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DashboardCountsData extends Data
{
    public function __construct(
        public int $waiting,
        public int $upcoming,
        public int $claims,
        public int $groups,
        public int $lists,
    ) {}
}
