<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ClaimTargetData extends Data
{
    public function __construct(
        public int $user_id,
        public ?string $name,
    ) {}
}
