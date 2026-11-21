<?php

namespace App\Data;

use App\Enums\DashboardPollStatus;
use App\Models\Poll;
use App\Support\WallClock;
use Spatie\LaravelData\Data;

class DashboardPollData extends Data
{
    public function __construct(
        public string $slug,
        public string $title,
        public DashboardPollStatus $status,
        public ?string $group_name,
        public int $answered,
        public int $total,
        public bool $is_closed,
        public ?string $answer_deadline,
    ) {}

    /**
     * Build a poll row with the person's own answer status (F-22, F-29).
     *
     * Expects the group and the person's own responses on the options to be
     * loaded. A poll without options counts as not answered; group_name is
     * null for a standalone poll. answer_deadline is a UTC instant, shown in the viewer's zone (F-08).
     */
    public static function fromPoll(Poll $poll): self
    {
        $total = $poll->options->count();
        $answered = $poll->options->filter(fn ($option) => $option->responses->isNotEmpty())->count();

        return new self(
            slug: $poll->slug,
            title: $poll->title,
            status: self::status($answered, $total),
            group_name: $poll->group?->name,
            answered: $answered,
            total: $total,
            is_closed: $poll->closed_at !== null,
            answer_deadline: WallClock::iso($poll->answer_deadline),
        );
    }

    /**
     * Map the answered option count to the own status.
     */
    private static function status(int $answered, int $total): DashboardPollStatus
    {
        if ($answered === 0) {
            return DashboardPollStatus::Unanswered;
        }

        if ($answered < $total) {
            return DashboardPollStatus::Partial;
        }

        return DashboardPollStatus::Answered;
    }
}
