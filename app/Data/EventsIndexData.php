<?php

namespace App\Data;

use App\Enums\ParticipationStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\LaravelData\Data;

class EventsIndexData extends Data
{
    /**
     * @param  array<int, DashboardEventData>  $events
     */
    public function __construct(
        public array $events,
        public EventsFiltersData $filters,
    ) {}

    /**
     * Build the page "All events" with the events the person takes part in, narrowed by the filters (F-32).
     *
     * Visibility follows the dashboard (F-22): events of the person's groups,
     * own standalone events (F-29) and events reached through a grant. Only
     * events with an accepted or pending participation count, so withdrawn
     * and declined events stay out,
     * and child events belong to their source event. Without "all", past
     * events are left out.
     */
    public static function forUser(User $user, EventsFiltersData $filters): self
    {
        $statuses = $filters->mine ? [ParticipationStatus::Accepted] : [ParticipationStatus::Accepted, ParticipationStatus::Pending];

        $events = Event::query()
            ->whereNull('parent_event_id')
            ->where(fn (Builder $query) => $query
                ->whereIn('group_id', $user->groups()->select('groups.id'))
                ->orWhere(fn (Builder $query) => $query->standaloneOrganizedBy($user))
                ->orWhere(fn (Builder $query) => $query->reachableThroughGrants($user)))
            ->whereHas('participations', fn (Builder $query) => $query
                ->where('user_id', $user->getKey())
                ->whereIn('status', $statuses))
            ->when(! $filters->all, fn (Builder $query) => $query->upcoming())
            ->with([
                'group',
                'participations' => fn ($query) => $query->where('status', ParticipationStatus::Accepted)->with('user:id,name')->orderBy('id'),
            ])
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get();

        return new self(
            events: $events->map(fn (Event $event) => DashboardEventData::fromEvent($event))->all(),
            filters: $filters,
        );
    }
}
