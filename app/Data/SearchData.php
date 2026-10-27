<?php

namespace App\Data;

use App\Models\Event;
use App\Models\Group;
use App\Models\Poll;
use App\Models\Roster;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class SearchData extends Data
{
    /**
     * How many records each type returns; the palette is a jump list, not a result page.
     */
    public const int LIMIT = 5;

    /**
     * Shorter queries return nothing, since one letter matches almost every record.
     */
    public const int MIN_LENGTH = 2;

    /**
     * @param  array<int, SearchSectionData>  $sections
     */
    public function __construct(
        public string $query,
        public array $sections,
    ) {}

    /**
     * Search the records the person may see by title (F-25).
     *
     * Every type follows the same visibility as its page: groups by
     * membership, polls through Poll::visibleTo(), events by group
     * membership and lists through the roster policy. Types without a
     * match are left out.
     */
    public static function forUser(User $user, string $query): self
    {
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return new self($query, []);
        }

        $locale = app()->getLocale();
        $groupIds = $user->groups()->pluck('groups.id')->all();

        $sections = [
            self::section('groups', self::groups($user, $query)->map(fn (Group $group) => new SearchResultData(
                id: 'group:'.$group->slug,
                title: $group->name,
                context: null,
                url: route('groups.show', $group),
            ))),
            self::section('polls', self::polls($user, $query)->map(fn (Poll $poll) => new SearchResultData(
                id: 'poll:'.$poll->slug,
                title: $poll->title,
                context: $poll->group->name,
                url: route('polls.show', $poll),
            ))),
            self::section('events', self::events($groupIds, $query)->map(fn (Event $event) => new SearchResultData(
                id: 'event:'.$event->slug,
                title: $event->title,
                context: self::formatDate($event->date, $locale).' · '.$event->group->name,
                url: route('events.show', $event),
            ))),
            self::section('lists', self::rosters($user, $groupIds, $query)->map(fn (Roster $roster) => new SearchResultData(
                id: 'list:'.$roster->slug,
                title: $roster->title,
                context: self::rosterContext($roster),
                url: route('rosters.show', $roster),
            ))),
        ];

        return new self($query, array_values(array_filter($sections)));
    }

    /**
     * Wrap the results of one type with its heading, or null without results.
     *
     * @param  Collection<int, SearchResultData>  $results
     */
    private static function section(string $type, Collection $results): ?SearchSectionData
    {
        if ($results->isEmpty()) {
            return null;
        }

        return new SearchSectionData($type, __('ui.search.type.'.$type), $results->values()->all());
    }

    /**
     * The person's groups whose name matches.
     *
     * @return Collection<int, Group>
     */
    private static function groups(User $user, string $query): Collection
    {
        return Group::query()
            ->whereHas('members', fn (Builder $member) => $member->whereKey($user->getKey()))
            ->where(self::matches('groups.name', $query))
            ->orderBy('groups.name')
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * The polls the person may see whose title matches, newest first (F-06, F-12).
     *
     * @return Collection<int, Poll>
     */
    private static function polls(User $user, string $query): Collection
    {
        return Poll::query()
            ->visibleTo($user)
            ->where(self::matches('polls.title', $query))
            ->with('group')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * The events of the person's groups whose title matches, latest date first (F-10).
     *
     * @param  array<int, int>  $groupIds
     * @return Collection<int, Event>
     */
    private static function events(array $groupIds, string $query): Collection
    {
        return Event::query()
            ->whereIn('group_id', $groupIds)
            ->where(self::matches('events.title', $query))
            ->with('group')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * The lists the person may see whose title matches, newest first (F-13).
     *
     * The query only narrows the candidates to lists the person could reach;
     * the roster policy decides, so event lists stay hidden from members
     * without participation.
     *
     * @param  array<int, int>  $groupIds
     * @return Collection<int, Roster>
     */
    private static function rosters(User $user, array $groupIds, string $query): Collection
    {
        $candidates = Roster::query()
            ->where(self::matches('rosters.title', $query))
            ->where(fn (Builder $scope) => $scope
                ->where('owner_id', $user->getKey())
                ->orWhereIn('group_id', $groupIds)
                ->orWhereHas('event', fn (Builder $event) => $event->whereIn('group_id', $groupIds))
                ->orWhereHas('items.claims', fn (Builder $claim) => $claim->where('user_id', $user->getKey())))
            ->with(['group', 'event.group']);

        return $candidates
            ->lazyByIdDesc(20, 'rosters.id', 'id')
            ->filter(fn (Roster $roster) => $user->can('view', $roster))
            ->take(self::LIMIT)
            ->values()
            ->collect();
    }

    /**
     * Build a constraint for rows whose column contains the search text.
     *
     * LIKE wildcards in the text are escaped, so "50%" matches literally.
     * The column is always a literal from this class, never user input.
     *
     * @param  literal-string  $column
     */
    private static function matches(string $column, string $query): Closure
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($query));

        return fn (Builder $builder) => $builder->whereRaw(new Expression("lower({$column}) like ? escape '!'"), ['%'.$escaped.'%']);
    }

    /**
     * Name where the list lives: its event, its group or "Standalone list".
     */
    private static function rosterContext(Roster $roster): string
    {
        if ($roster->event_id !== null) {
            return $roster->event->title;
        }

        if ($roster->group_id !== null) {
            return $roster->group->name;
        }

        return __('ui.search.standalone');
    }

    /**
     * Format a date as "15.10.2026" in German and "15 Oct 2026" in English.
     */
    private static function formatDate(CarbonInterface $date, string $locale): string
    {
        return $date->locale($locale)->translatedFormat($locale === 'de' ? 'd.m.Y' : 'j M Y');
    }
}
