<?php

namespace App\Events;

use App\Models\Event;
use App\Models\Group;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class DashboardChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance for a change to what the dashboards of the given people show (F-21, F-22).
     *
     * @param  array<int, int>  $userIds
     */
    public function __construct(
        public ?int $groupId,
        public array $userIds,
    ) {}

    /**
     * Dispatch the event to every member of the group.
     *
     * Members resolve before the commit, so a deleted poll or event still
     * reaches the people whose dashboards showed it.
     */
    public static function forGroup(Group|int $group): void
    {
        $group = $group instanceof Group ? $group : Group::query()->find($group);

        if ($group === null) {
            return;
        }

        self::dispatch($group->getKey(), $group->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all());
    }

    /**
     * Dispatch the event to everyone whose dashboard shows the poll or event (F-21, F-22, F-29).
     */
    public static function forItem(Poll|Event $item): void
    {
        self::dispatch($item->group_id, self::recipientsOf($item));
    }

    /**
     * The people whose dashboards show the poll or event: the group's members, or for a standalone item its organizer, grant holders and participants (F-29).
     *
     * Callers that delete the item resolve them first, so the change still
     * reaches the people whose dashboards showed it.
     *
     * @return array<int, int>
     */
    public static function recipientsOf(Poll|Event $item): array
    {
        if ($item->group_id !== null) {
            return Group::query()->find($item->group_id)?->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all() ?? [];
        }

        $people = $item->insiders()->toBase()->concat($item->guests());

        if ($item instanceof Event) {
            $people = $people->concat($item->participations()->with('user')->get()->pluck('user')->filter());
        }

        return $people->map(fn (User $person) => (int) $person->getKey())->unique()->values()->all();
    }

    /**
     * Get the private account channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn (int $userId) => new PrivateChannel('App.Models.User.'.$userId), $this->userIds);
    }

    /**
     * Skip the broadcast when nobody would hear it.
     */
    public function broadcastWhen(): bool
    {
        return $this->userIds !== [];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'dashboard.changed';
    }

    /**
     * Get the data to broadcast: ids only, the dashboard reloads its own props.
     *
     * @return array{group_id: int|null}
     */
    public function broadcastWith(): array
    {
        return ['group_id' => $this->groupId];
    }
}
