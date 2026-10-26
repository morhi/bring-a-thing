<?php

namespace App\Actions\Events;

use App\Enums\ParticipationStatus;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\RosterItemClaim;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetParticipation
{
    /**
     * Set a person's participation status on the event (F-11).
     *
     * Only the person's own decline releases their claims on the event's
     * lists, and only after they confirmed it: without confirmation the
     * decline is rejected with the affected items, so nothing is released
     * by surprise. A status set by someone else never releases claims.
     *
     * @throws ValidationException when an own decline would release claims and was not confirmed
     */
    public function handle(Event $event, User $person, ParticipationStatus $status, User $actor, bool $confirmRelease = false): EventParticipation
    {
        $releasesClaims = $status === ParticipationStatus::Declined && $person->is($actor);
        $claims = $releasesClaims ? $event->claimsOf($person) : collect();

        if ($claims->isNotEmpty() && ! $confirmRelease) {
            throw ValidationException::withMessages([
                'confirm_release' => 'Declining releases your claims: '.$claims->map(fn (RosterItemClaim $claim) => $claim->rosterItem->name)->join(', ').'.',
            ]);
        }

        return DB::transaction(function () use ($event, $person, $status, $claims) {
            $claims->each(fn (RosterItemClaim $claim) => $claim->delete());

            return $event->participations()->updateOrCreate(
                ['user_id' => $person->getKey()],
                ['status' => $status],
            );
        });
    }
}
