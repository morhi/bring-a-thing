<?php

namespace App\Data;

use App\Enums\PollType;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollResponse;
use App\Models\User;
use Spatie\LaravelData\Data;

class GroupPollData extends Data
{
    public function __construct(
        public string $slug,
        public string $title,
        public PollType $type,
        public bool $is_closed,
        public bool $is_fixed,
        public int $option_count,
        public int $answered_count,
        public int $audience_count,
        public ?string $follow_up_event_title,
    ) {}

    /**
     * Build a poll row of the group page (F-06 to F-09, F-12).
     *
     * answered_count counts audience members with at least one answer;
     * follow_up_event_title is set for follow-up polls.
     */
    public static function fromPoll(Poll $poll): self
    {
        $audienceIds = $poll->audience()->map(fn (User $member) => $member->getKey());
        $respondentIds = $poll->options
            ->flatMap(fn (PollOption $option) => $option->responses->map(fn (PollResponse $response) => $response->user_id))
            ->unique();

        return new self(
            slug: $poll->slug,
            title: $poll->title,
            type: $poll->type,
            is_closed: $poll->isClosed(),
            is_fixed: $poll->options->contains(fn (PollOption $option) => $option->isFixed()),
            option_count: $poll->options->count(),
            answered_count: $respondentIds->intersect($audienceIds)->count(),
            audience_count: $audienceIds->count(),
            follow_up_event_title: $poll->event?->title,
        );
    }
}
