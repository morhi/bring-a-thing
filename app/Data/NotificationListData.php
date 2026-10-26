<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;

class NotificationListData extends Data
{
    /**
     * How many entries one panel page holds; the client follows next_page instead of choosing a size.
     */
    public const int PER_PAGE = 20;

    /**
     * @param  array<int, NotificationData>  $notifications
     */
    public function __construct(
        public array $notifications,
        public int $unread,
        public int $per_page,
        public ?int $next_page,
    ) {}

    /**
     * Build one page of the bell panel, newest first (F-23).
     */
    public static function forUser(User $user, int $page): self
    {
        $paginator = $user->notifications()->simplePaginate(self::PER_PAGE, page: $page);

        return new self(
            notifications: NotificationData::collectFor($paginator->getCollection()),
            unread: $user->unreadNotifications()->count(),
            per_page: self::PER_PAGE,
            next_page: $paginator->hasMorePages() ? $page + 1 : null,
        );
    }
}
