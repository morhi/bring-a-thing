<?php

namespace App\Actions\Polls;

use App\Enums\ParticipationStatus;
use App\Mail\FixedDatesMail;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\Poll;
use App\Models\User;
use App\Support\WallClock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class SendFixedDatesMail
{
    /**
     * Queue one summary mail with every fixed date to each participant with an email (F-30).
     *
     * The actor is skipped, and so is a recipient who may not view any of
     * their events, so the mail never reveals what the policies hide.
     *
     * @param  Collection<int, Event>  $events
     */
    public function handle(Poll $poll, Collection $events, User $actor): void
    {
        $sorted = $events->sortBy(fn (Event $event) => $event->startsAt()->getTimestamp())->values();

        $participations = EventParticipation::query()
            ->whereIn('event_id', $events->pluck('id'))
            ->whereIn('status', [ParticipationStatus::Accepted, ParticipationStatus::Pending])
            ->with('user')
            ->get();

        $recipients = $participations
            ->pluck('user')
            ->filter()
            ->unique(fn (User $user) => $user->getKey())
            ->reject(fn (User $user) => $user->is($actor) || $user->email === null)
            ->filter(fn (User $user) => $events->contains(fn (Event $event) => $user->can('view', $event)));

        foreach ($recipients as $user) {
            Mail::to($user)
                ->locale($user->language ?? app()->getLocale())
                ->queue(new FixedDatesMail($poll->title, route('polls.show', $poll), $sorted, WallClock::zoneOf($user)));
        }
    }
}
