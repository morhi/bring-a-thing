<?php

namespace App\Data;

use App\Enums\ParticipationStatus;
use Spatie\LaravelData\Data;

class ParticipationData extends Data
{
    public function __construct(
        public int $user_id,
        public ?string $name,
        public ?ParticipationStatus $status,
    ) {}
}
