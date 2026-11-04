<?php

namespace App\Data;

use App\Models\Event;
use App\Models\Poll;
use Spatie\LaravelData\Data;

class EntityInviteLinkData extends Data
{
    public function __construct(
        public ?string $url,
    ) {}

    /**
     * Build the invite link state of an event or poll for its managers (F-26, F-27).
     *
     * A null url means no link exists yet; creating it is the first action.
     */
    public static function fromEntity(Event|Poll $entity): self
    {
        return new self(
            url: $entity->inviteUrl(),
        );
    }
}
