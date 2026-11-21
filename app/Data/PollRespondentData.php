<?php

namespace App\Data;

use App\Enums\PollResponseStatus;
use Spatie\LaravelData\Data;

class PollRespondentData extends Data
{
    /**
     * @param  array<string, PollResponseStatus>  $answers  answers keyed by option slug; unanswered options are missing
     * @param  array<string, string>  $notes  notes on own answers keyed by option slug; options without a note are missing (F-06)
     * @param  bool  $in_audience  false for former members whose answers stay (F-04); only audience rows can be edited (F-06)
     */
    public function __construct(
        public int $user_id,
        public ?string $name,
        public array $answers,
        public array $notes,
        public bool $is_complete,
        public bool $in_audience,
    ) {}
}
