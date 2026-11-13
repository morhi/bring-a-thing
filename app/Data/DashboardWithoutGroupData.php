<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DashboardWithoutGroupData extends Data
{
    public function __construct(
        public int $events_count,
        public int $polls_count,
        public int $lists_count,
    ) {}
}
