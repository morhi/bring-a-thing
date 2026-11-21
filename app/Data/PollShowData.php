<?php

namespace App\Data;

use App\Enums\PollResponseStatus;
use App\Models\Comment;
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
     * @param  array<int, CommentData>  $comments
     */
    public function __construct(
        public PollData $poll,
        public array $respondents,
        public int $audience_count,
        public bool $answers_without_acceptance,
        public array $own_answers,
        public array $best_option_slugs,
        public bool $show_no_counts,
        public array $fixed_events,
        public PollPermissionsData $permissions,
        public ?EntityInviteLinkData $invite_link,
        public array $comments,
        public int $note_max_length,
    ) {}

    /**
     * Build the poll page for the given user (F-06 to F-09).
     *
     * respondents holds one row per person in the poll's audience, with the
     * current user first, plus former members whose responses stay (F-04).
     * On a follow-up poll the audience is the source event's accepted
     * participants with the poll's organizer and the event's managers;
     * anyone who left it drops out of the grid and the totals (F-12).
     * audience_count feeds the audience note; answers_without_acceptance is set
     * for a viewer of a follow-up poll who has not accepted the source event,
     * such as a guest of the poll's own link, so the note does not claim otherwise (F-12).
     * show_no_counts is set when no answers decided between options tied on
     * yes, so the grid explains the best option (F-06). Guests with a grant
     * (F-26, F-27) belong to the audience; invite_link is only set for
     * editors (F-27). comments is the poll's own thread, which everyone who
     * sees the poll may read (F-20). note_max_length caps the notes on
     * answers, which every respondent row carries for everyone (F-06).
     */
    public static function forUser(Poll $poll, User $user): self
    {
        $poll->loadMissing('group.members', 'options.responses.user', 'options.event', 'event.participations', 'comments.user');
        $pollData = PollData::fromPoll($poll);
        $optionCount = $poll->options->count();

        $answersByUser = $poll->options
            ->flatMap(fn (PollOption $option) => $option->responses->map(fn (PollResponse $response) => [
                'user_id' => $response->user_id,
                'option_slug' => $option->slug,
                'status' => $response->status,
                'note' => $response->note,
            ]))
            ->groupBy('user_id');
        $notesByUser = $answersByUser->map(fn ($answers) => $answers->whereNotNull('note')->pluck('note', 'option_slug')->all());
        $answersByUser = $answersByUser->map(fn ($answers) => $answers->pluck('status', 'option_slug')->all());

        $audience = $poll->audience();
        $permissions = PollPermissionsData::forUser($poll, $user);
        $formerResponders = $poll->options
            ->flatMap(fn (PollOption $option) => $option->responses)
            ->filter(fn (PollResponse $response) => $poll->countsResponseFrom($response->user_id))
            ->reject(fn (PollResponse $response) => $audience->contains('id', $response->user_id))
            ->map(fn (PollResponse $response) => $response->user)
            ->filter()
            ->unique('id');

        $respondents = $audience->concat($formerResponders)
            ->sortBy(fn (User $person) => $person->id === $user->id ? 0 : 1)
            ->map(fn (User $person) => new PollRespondentData(
                user_id: $person->id,
                name: $person->name,
                answers: $answersByUser->get($person->id, []),
                notes: $notesByUser->get($person->id, []),
                is_complete: count($answersByUser->get($person->id, [])) >= $optionCount,
                in_audience: $audience->contains('id', $person->id),
            ))
            ->values();

        return new self(
            poll: $pollData,
            respondents: $respondents->all(),
            audience_count: $audience->count(),
            answers_without_acceptance: $poll->event !== null && ! $poll->event->hasAccepted($user),
            own_answers: $answersByUser->get($user->id, []),
            best_option_slugs: $poll->bestOptions()->pluck('slug')->all(),
            show_no_counts: $poll->bestIsDecidedByNoAnswers(),
            fixed_events: $poll->fixedEvents()->with('group')->get()->map(fn (Event $event) => EventSummaryData::fromEvent($event))->all(),
            permissions: $permissions,
            invite_link: $permissions->can_manage_invite_link ? EntityInviteLinkData::fromEntity($poll) : null,
            comments: $poll->comments->map(fn (Comment $comment) => CommentData::forUser($comment->setRelation('commentable', $poll), $user))->all(),
            note_max_length: PollResponse::NOTE_MAX_LENGTH,
        );
    }
}
