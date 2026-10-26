<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class RosterParentData extends Data
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
    ) {}
}
