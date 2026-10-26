<?php

namespace App\Actions\Polls;

use App\Enums\PollGranularity;
use App\Enums\PollType;
use App\Models\Event;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateFollowUpPoll
{
    public function __construct(private CreatePollOptions $createOptions) {}

    /**
     * Create a date finder or attendance poll within the event (F-12).
     *
     * The poll belongs to the event's group and keeps the event as its
     * source, which limits its audience to the event's accepted
     * participants and managers, and attaches dates fixed from it to the
     * event as child events (F-09).
     *
     * @param  array{title: string, type: PollType, granularity: PollGranularity}  $attributes
     * @param  array<int, array{date: string, starts_at?: string|null, ends_at?: string|null, label?: string|null}>  $options
     */
    public function handle(Event $event, User $organizer, array $attributes, bool $usesExplicitOptions, array $options, ?string $startsOn, ?string $endsOn): Poll
    {
        return DB::transaction(function () use ($event, $organizer, $attributes, $usesExplicitOptions, $options, $startsOn, $endsOn) {
            $poll = Poll::query()->make(['title' => $attributes['title']]);
            $poll->type = $attributes['type'];
            $poll->granularity = $attributes['granularity'];
            $poll->organizer_id = $organizer->getKey();
            $poll->group_id = $event->group_id;
            $poll->event_id = $event->getKey();
            $poll->save();

            $this->createOptions->handle($poll, $usesExplicitOptions, $options, $startsOn, $endsOn);

            return $poll;
        });
    }
}
