<?php

namespace App\Data;

use App\Enums\PollResponseStatus;
use Spatie\LaravelData\Data;

class PollRespondentData extends Data
{
    /**
     * @param  array<string, PollResponseStatus>  $answers  answers keyed by option slug; unanswered options are missing
     */
    public function __construct(
        public int $user_id,
        public ?string $name,
        public array $answers,
        public bool $is_complete,
    ) {}
}
