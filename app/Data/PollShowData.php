<?php

namespace App\Data;

use App\Enums\PollResponseStatus;
use App\Models\Event;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollResponse;
use App\Models\User;
use Spatie\LaravelData\Data;

class PollShowData extends Data
{
    /**
     * @param  array<int, PollRespondentData>  $respondents
     * @param  array<string, PollResponseStatus>  $own_answers
     * @param  array<int, string>  $best_option_slugs
     * @param  array<int, EventSummaryData>  $fixed_events
     */
    public function __construct(
        public PollData $poll,
        public array $respondents,
        public int $audience_count,
        public array $own_answers,
        public array $best_option_slugs,
        public bool $show_no_counts,
        public array $fixed_events,
        public PollPermissionsData $permissions,
    ) {}

    /**
     * Build the poll page for the given user (F-06 to F-09).
     *
     * respondents holds one row per person in the poll's audience, with the
     * current user first, plus former members whose responses stay (F-04).
     * On a follow-up poll the audience is the source event's accepted
     * participants with the poll's organizer and the event's managers;
     * anyone who left it drops out of the grid and the totals (F-12).
     * audience_count feeds the audience note.
     * show_no_counts is set when no answers decided between options tied on
     * yes, so the grid explains the best option (F-06).
     */
    public static function forUser(Poll $poll, User $user): self
    {
        $poll->loadMissing('group.members', 'options.responses.user', 'options.event', 'event.participations');
        $pollData = PollData::fromPoll($poll);
        $optionCount = $poll->options->count();

        $answersByUser = $poll->options
            ->flatMap(fn (PollOption $option) => $option->responses->map(fn (PollResponse $response) => [
                'user_id' => $response->user_id,
                'option_slug' => $option->slug,
                'status' => $response->status,
            ]))
            ->groupBy('user_id')
            ->map(fn ($answers) => $answers->pluck('status', 'option_slug')->all());

        $audience = $poll->audience();
        $formerResponders = $poll->options
            ->flatMap(fn (PollOption $option) => $option->responses)
            ->filter(fn (PollResponse $response) => $poll->countsResponseFrom($response->user_id))
            ->reject(fn (PollResponse $response) => $poll->group->members->contains('id', $response->user_id))
            ->map(fn (PollResponse $response) => $response->user)
            ->filter()
            ->unique('id');

        $respondents = $audience->concat($formerResponders)
            ->sortBy(fn (User $person) => $person->id === $user->id ? 0 : 1)
            ->map(fn (User $person) => new PollRespondentData(
                user_id: $person->id,
                name: $person->name,
                answers: $answersByUser->get($person->id, []),
                is_complete: count($answersByUser->get($person->id, [])) >= $optionCount,
            ))
            ->values();

        return new self(
            poll: $pollData,
            respondents: $respondents->all(),
            audience_count: $audience->count(),
            own_answers: $answersByUser->get($user->id, []),
            best_option_slugs: $poll->bestOptions()->pluck('slug')->all(),
            show_no_counts: $poll->bestIsDecidedByNoAnswers(),
            fixed_events: $poll->fixedEvents()->with('group')->get()->map(fn (Event $event) => EventSummaryData::fromEvent($event))->all(),
            permissions: PollPermissionsData::forUser($poll, $user),
        );
    }
}
