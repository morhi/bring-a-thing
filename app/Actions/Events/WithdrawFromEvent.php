<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WithdrawFromEvent
{
    public function __construct(private ReleaseEventClaims $releaseClaims) {}

    /**
     * Remove the person's own participation and release their claims on the event's lists (F-11).
     *
     * Without a participation the event leaves the person's dashboard (F-22).
     */
    public function handle(Event $event, User $person): void
    {
        DB::transaction(function () use ($event, $person) {
            $this->releaseClaims->handle($event->claimsOf($person));

            $event->participations()->where('user_id', $person->getKey())->delete();
        });
    }
}
