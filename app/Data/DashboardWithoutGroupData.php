<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DashboardWithoutGroupData extends Data
{
    public function __construct(
        public int $lists_count,
    ) {}
}
