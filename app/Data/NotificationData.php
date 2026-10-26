<?php

namespace App\Data;

use App\Enums\NotificationKind;
use App\Models\Event;
use App\Models\Group;
use App\Models\Poll;
use App\Models\Roster;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

class NotificationData extends Data
{
    /**
     * The pages a notification can open, keyed by the stored subject type.
     *
     * @var array<string, array{model: class-string<Model>, route: string}>
     */
    private const array SUBJECTS = [
        'group' => ['model' => Group::class, 'route' => 'groups.show'],
        'poll' => ['model' => Poll::class, 'route' => 'polls.show'],
        'event' => ['model' => Event::class, 'route' => 'events.show'],
        'roster' => ['model' => Roster::class, 'route' => 'rosters.show'],
    ];

    /**
     * One entry of the bell panel (F-23).
     *
     * The client renders `key` with `params` through t(), so the entry
     * follows the viewer's current language (F-24); `text` is the same
     * sentence rendered on the server, for live toasts and fallbacks. The
     * url is null once the subject was deleted.
     */
    public function __construct(
        public string $id,
        public NotificationKind $kind,
        public string $key,
        public NotificationParamsData $params,
        public string $text,
        public ?string $url,
        public ?string $read_at,
        public string $created_at,
    ) {}

    /**
     * Build a panel entry from the stored notification data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromStored(string $id, array $data, CarbonInterface $createdAt, ?CarbonInterface $readAt, ?string $subjectSlug, string $locale): self
    {
        $kind = NotificationKind::from($data['kind']);
        $params = NotificationParamsData::fromStored($data['params'] ?? []);
        $route = self::SUBJECTS[$data['subject_type'] ?? '']['route'] ?? null;

        return new self(
            id: $id,
            kind: $kind,
            key: $kind->translationKey(),
            params: $params,
            text: __('ui.'.$kind->translationKey(), $params->replacements($locale), $locale),
            url: $route !== null && $subjectSlug !== null ? route($route, $subjectSlug) : null,
            read_at: $readAt?->toIso8601String(),
            created_at: $createdAt->toIso8601String(),
        );
    }

    /**
     * Build panel entries in the current language, looking up each subject type's slugs in one query.
     *
     * Entries with an unknown kind are skipped, so a stale row never breaks the panel.
     *
     * @param  Collection<int, DatabaseNotification>  $notifications
     * @return array<int, self>
     */
    public static function collectFor(Collection $notifications): array
    {
        $slugs = $notifications
            ->groupBy(fn (DatabaseNotification $notification) => $notification->data['subject_type'] ?? '')
            ->map(fn (Collection $group, string $type) => self::slugsOf($type, $group->pluck('data.subject_id')->filter()->all()));

        return $notifications
            ->filter(fn (DatabaseNotification $notification) => NotificationKind::tryFrom($notification->data['kind'] ?? '') !== null)
            ->map(fn (DatabaseNotification $notification) => self::fromStored(
                id: $notification->id,
                data: $notification->data,
                createdAt: $notification->created_at,
                readAt: $notification->read_at,
                subjectSlug: $slugs->get($notification->data['subject_type'] ?? '', collect())->get($notification->data['subject_id'] ?? 0),
                locale: app()->getLocale(),
            ))
            ->values()
            ->all();
    }

    /**
     * The stored subject type of a model, e.g. "event".
     */
    public static function subjectTypeOf(Model $subject): string
    {
        foreach (self::SUBJECTS as $type => $definition) {
            if ($subject instanceof $definition['model']) {
                return $type;
            }
        }

        throw new InvalidArgumentException('Unsupported notification subject '.$subject::class.'.');
    }

    /**
     * The slugs of the still existing subjects of one type, keyed by id.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, string>
     */
    private static function slugsOf(string $type, array $ids): Collection
    {
        $model = self::SUBJECTS[$type]['model'] ?? null;

        if ($model === null || $ids === []) {
            return collect();
        }

        return $model::query()->whereKey($ids)->pluck('slug', 'id');
    }
}
