<?php

namespace App\Actions\Events;

use App\Events\ClaimChanged;
use App\Models\RosterItemClaim;
use Illuminate\Support\Collection;

class ReleaseEventClaims
{
    /**
     * Release a person's claims on an event's lists and broadcast each one to its list (F-11, F-21).
     *
     * @param  Collection<int, RosterItemClaim>  $claims
     */
    public function handle(Collection $claims): void
    {
        $claims->each(function (RosterItemClaim $claim) {
            $claim->delete();
            ClaimChanged::dispatch($claim->rosterItem->roster_id, $claim->roster_item_id, $claim->user_id);
        });
    }
}
