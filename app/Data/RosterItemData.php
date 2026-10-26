<?php

namespace App\Data;

use App\Models\Comment;
use App\Models\RosterItem;
use App\Models\RosterItemClaim;
use App\Models\RosterItemCustomFieldValue;
use App\Models\User;
use Spatie\LaravelData\Data;

class RosterItemData extends Data
{
    /**
     * @param  array<int, RosterClaimData>  $claims
     * @param  array<int, CustomFieldValueData>  $custom_field_values
     * @param  array<int, CommentData>  $comments
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public ?float $quantity,
        public ?string $unit,
        public ?string $notes,
        public ?string $date,
        public ?string $effective_date,
        public bool $is_past,
        public bool $is_task,
        public float $claimed_quantity,
        public ?float $remaining_quantity,
        public bool $is_fully_claimed,
        public array $claims,
        public array $custom_field_values,
        public ?AttendanceDayData $attendance_day,
        public bool $attendance_warning,
        public bool $can_claim,
        public array $comments,
    ) {}

    /**
     * Build an item of the list page for the viewing user (F-14, F-15, F-18).
     *
     * An item without a quantity is a task with at most one claimant, so
     * a claimed task is fully claimed. can_claim combines the list-level
     * permission with the item's state: a task claimed by someone else
     * offers no claim. attendance_warning asks for "Claim anyway" when the
     * user answered no or nothing for the item's effective attendance day.
     */
    public static function forUser(RosterItem $item, User $user, bool $userMayClaim): self
    {
        $data = self::build($item, true);
        $ownClaim = $item->claims->firstWhere('user_id', $user->getKey());
        $openToUser = $ownClaim !== null || ! $data->is_fully_claimed;

        $data->attendance_warning = $data->attendance_day?->warns($user) ?? false;
        $data->can_claim = $userMayClaim && $openToUser;
        $data->comments = $item->comments->map(fn (Comment $comment) => CommentData::forUser($comment, $user))->all();

        return $data;
    }

    /**
     * Build an item of the shared list for a share link visitor (F-19).
     *
     * Leaves out user IDs, comments and attendance details; claiming opens the login on the same page.
     */
    public static function forShareLink(RosterItem $item): self
    {
        $data = self::build($item, false);
        $data->attendance_day = null;
        $data->can_claim = ! $data->is_fully_claimed;

        return $data;
    }

    /**
     * Build the parts shared by the list page and the shared list.
     */
    private static function build(RosterItem $item, bool $withUserIds): self
    {
        $quantity = $item->quantity === null ? null : (float) $item->quantity;
        $claimed = (float) $item->claims->sum('quantity');
        $remaining = $quantity === null ? null : max(0.0, round($quantity - $claimed, 2));
        $effectiveDate = $item->date ?? $item->roster->date;
        $attendanceOption = $item->effective_attendance_poll_option;

        return new self(
            id: $item->id,
            slug: $item->slug,
            name: $item->name,
            quantity: $quantity,
            unit: $item->unit,
            notes: $item->notes,
            date: $item->date?->format('Y-m-d'),
            effective_date: $effectiveDate?->format('Y-m-d'),
            is_past: (bool) $item->is_past,
            is_task: $quantity === null,
            claimed_quantity: $claimed,
            remaining_quantity: $remaining,
            is_fully_claimed: $quantity === null ? $item->claims->isNotEmpty() : $remaining <= 0,
            claims: $item->claims->map(fn (RosterItemClaim $claim) => RosterClaimData::fromClaim($claim, $withUserIds))->all(),
            custom_field_values: $item->customFieldValues
                ->map(fn (RosterItemCustomFieldValue $value) => new CustomFieldValueData($value->custom_field_id, $value->value))
                ->all(),
            attendance_day: $attendanceOption ? AttendanceDayData::fromOption($attendanceOption) : null,
            attendance_warning: false,
            can_claim: false,
            comments: [],
        );
    }
}
