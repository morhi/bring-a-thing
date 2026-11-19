<?php

namespace App\Actions\Calendar;

use App\Enums\ParticipationStatus;
use App\Enums\PollResponseStatus;
use App\Models\Event;
use App\Models\PollResponse;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event as CalendarEvent;
use Spatie\IcalendarGenerator\Enums\EventStatus;

class BuildCalendarFeed
{
    /**
     * Build the ICS feed of the user's events and reserved poll options (F-34).
     *
     * Texts follow the user's language. Times are floating, without a
     * timezone, so calendar apps show them as entered.
     */
    public function handle(User $user): string
    {
        $original = App::getLocale();
        App::setLocale($user->preferredLocale() ?? $original);

        try {
            return $this->build($user);
        } finally {
            App::setLocale($original);
        }
    }

    /**
     * Assemble the calendar in the currently active locale.
     */
    private function build(User $user): string
    {
        $calendar = Calendar::create(__('ui.calendar.name'))->withoutTimezone();

        foreach ($this->events($user) as $event) {
            $calendar->event($this->eventEntry($event, $user));
        }

        foreach ($this->reservedResponses($user) as $response) {
            $calendar->event($this->reservedEntry($response));
        }

        return $calendar->get();
    }

    /**
     * The events the user can open and takes part in with "accepted" or "pending", child events included.
     *
     * @return Collection<int, Event>
     */
    private function events(User $user): Collection
    {
        return Event::query()
            ->visibleTo($user)
            ->whereHas('participations', fn (Builder $query) => $query
                ->where('user_id', $user->getKey())
                ->whereIn('status', [ParticipationStatus::Accepted, ParticipationStatus::Pending]))
            ->with(['participations' => fn ($query) => $query->where('user_id', $user->getKey())])
            ->orderBy('date')
            ->get();
    }

    /**
     * The user's yes and maybe answers on open polls whose option is not fixed to an event.
     *
     * @return Collection<int, PollResponse>
     */
    private function reservedResponses(User $user): Collection
    {
        return PollResponse::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', [PollResponseStatus::Yes, PollResponseStatus::Maybe])
            ->whereHas('option', fn (Builder $option) => $option
                ->whereDoesntHave('event')
                ->whereHas('poll', fn (Builder $poll) => $poll->whereNull('closed_at')->visibleTo($user)))
            ->with('option.poll')
            ->get();
    }

    /**
     * Map an event to a calendar entry, confirmed when accepted and tentative when pending.
     */
    private function eventEntry(Event $event, User $user): CalendarEvent
    {
        $entry = CalendarEvent::create($event->title)
            ->uniqueIdentifier('event-'.$event->slug.'@'.$this->host())
            ->status($event->hasAccepted($user) ? EventStatus::Confirmed : EventStatus::Tentative)
            ->url(route('events.show', $event))
            ->description(trim(($event->description ?? '')."\n\n".route('events.show', $event)));

        if ($event->location !== null && $event->location !== '') {
            $entry->address($event->location);
        }

        return $this->withTimes($entry, $event->date, $event->starts_at, $event->end_date ?? $event->date, $event->ends_at);
    }

    /**
     * Map an answered poll option to a tentative "Reserved" entry.
     */
    private function reservedEntry(PollResponse $response): CalendarEvent
    {
        $option = $response->option;
        $poll = $option->poll;
        $title = $option->label ? "{$poll->title} ({$option->label})" : $poll->title;
        $answer = __('ui.poll.answer.'.$response->status->value);

        $entry = CalendarEvent::create(__('ui.calendar.reserved', ['title' => $title]))
            ->uniqueIdentifier('poll-option-'.$option->slug.'@'.$this->host())
            ->status(EventStatus::Tentative)
            ->url(route('polls.show', $poll))
            ->description(__('ui.calendar.yourAnswer', ['answer' => $answer])."\n\n".route('polls.show', $poll));

        return $this->withTimes($entry, $option->date, $option->starts_at, $option->date, $option->ends_at);
    }

    /**
     * Set all-day dates with an exclusive end, or floating start and end times.
     */
    private function withTimes(CalendarEvent $entry, CarbonInterface $date, ?string $startsAt, CarbonInterface $endDate, ?string $endsAt): CalendarEvent
    {
        if ($startsAt === null) {
            return $entry->fullDay()->startsAt($date->copy()->startOfDay())->endsAt($endDate->copy()->addDay()->startOfDay());
        }

        $entry->startsAt(Carbon::parse($date->format('Y-m-d').' '.$startsAt));

        if ($endsAt === null && $endDate->isSameDay($date)) {
            return $entry;
        }

        // A multi-day span without an end time ends after its last day, so it is not cut to the first.
        if ($endsAt === null) {
            return $entry->endsAt($endDate->copy()->addDay()->startOfDay());
        }

        return $entry->endsAt(Carbon::parse($endDate->format('Y-m-d').' '.$endsAt));
    }

    /**
     * The host of the configured app url, for stable UIDs.
     */
    private function host(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
    }
}
