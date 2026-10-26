<?php

namespace App\Data;

use App\Enums\PollType;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;
use Spatie\LaravelData\Data;

class AttendanceOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $poll_title,
        public string $date,
        public ?string $starts_at,
        public ?string $label,
    ) {}

    /**
     * List the days of the group's attendance polls a list or item can link to (F-18).
     *
     * Empty without a group or without attendance polls, so the field does not appear (section 8).
     * Only polls open to the user count, so a follow-up poll stays hidden from list editors outside its audience (F-12).
     *
     * @return array<int, self>
     */
    public static function forGroup(?Group $group, User $user): array
    {
        if ($group === null) {
            return [];
        }

        return $group->polls()
            ->where('type', PollType::Attendance)
            ->visibleTo($user)
            ->with('options')
            ->get()
            ->flatMap(fn (Poll $poll) => $poll->options->map(fn (PollOption $option) => new self(
                id: $option->id,
                poll_title: $poll->title,
                date: $option->date->format('Y-m-d'),
                starts_at: EventSummaryData::time($option->starts_at),
                label: $option->label,
            )))
            ->values()
            ->all();
    }
}
