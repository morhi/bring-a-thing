<?php

namespace App\Data;

use App\Enums\ParticipationStatus;
use App\Models\Comment;
use App\Models\CustomField;
use App\Models\EventParticipation;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\RosterItemClaim;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class RosterShowData extends Data
{
    /**
     * @param  array<int, RosterItemData>  $items
     * @param  array<int, CustomFieldData>  $custom_fields
     * @param  array<int, CommentData>  $comments
     * @param  array<int, ClaimTargetData>  $claim_targets
     * @param  array<int, AttendanceOptionData>  $attendance_options
     */
    public function __construct(
        public RosterData $roster,
        public array $items,
        public array $custom_fields,
        public array $comments,
        public array $claim_targets,
        public array $attendance_options,
        public bool $is_all_taken,
        public RosterPermissionsData $permissions,
    ) {}

    /**
     * Build the list page for the given user (F-13 to F-18, F-20).
     *
     * Items come sorted by their own date so the page can group them.
     * claim_targets lists the people a manager may claim for: on event
     * lists only accepted participants (F-15), on group lists the members,
     * on standalone lists the owner and everyone holding a claim.
     */
    public static function forUser(Roster $roster, User $user): self
    {
        $roster->load([
            'owner',
            'group.members',
            'event.participations.user',
            'event.group',
            'customFields',
            'attendancePollOption.poll',
            'attendancePollOption.responses',
            'comments.user',
            'items' => fn ($query) => $query->with([
                'claims.user',
                'customFieldValues',
                'attendancePollOption.poll',
                'attendancePollOption.responses',
                'comments.user',
            ])->orderBy('date'),
        ]);

        $permissions = RosterPermissionsData::forUser($roster, $user);
        $items = $roster->items->map(fn (RosterItem $item) => RosterItemData::forUser($item, $user, $permissions->can_claim));

        return new self(
            roster: RosterData::forUser($roster, $user),
            items: $items->all(),
            custom_fields: $roster->customFields->map(fn (CustomField $field) => CustomFieldData::fromField($field))->all(),
            comments: $roster->comments->map(fn (Comment $comment) => CommentData::forUser($comment, $user))->all(),
            claim_targets: $permissions->can_manage_claims ? self::claimTargets($roster)->all() : [],
            attendance_options: $permissions->can_add_items || $permissions->can_edit_items ? AttendanceOptionData::forGroup($roster->group, $user) : [],
            is_all_taken: $items->isNotEmpty() && $items->every(fn (RosterItemData $item) => $item->is_fully_claimed),
            permissions: $permissions,
        );
    }

    /**
     * The people a manager may claim for on this list (F-15).
     *
     * @return Collection<int, ClaimTargetData>
     */
    private static function claimTargets(Roster $roster): Collection
    {
        $people = match (true) {
            $roster->event_id !== null => $roster->event->participations
                ->where('status', ParticipationStatus::Accepted)
                ->map(fn (EventParticipation $participation) => $participation->user),
            $roster->group_id !== null => $roster->group->members,
            default => $roster->items
                ->flatMap(fn (RosterItem $item) => $item->claims->map(fn (RosterItemClaim $claim) => $claim->user))
                ->push($roster->owner),
        };

        return $people
            ->filter()
            ->unique('id')
            ->map(fn (User $person) => new ClaimTargetData($person->id, $person->name))
            ->values();
    }
}
