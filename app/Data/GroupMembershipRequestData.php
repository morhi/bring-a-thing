<?php

namespace App\Data;

use App\Models\GroupMembershipRequest;
use Spatie\LaravelData\Data;

class GroupMembershipRequestData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $email,
    ) {}

    /**
     * Build a pending request row for the owner, with the requester's id as the resolve key (F-03).
     */
    public static function fromRequest(GroupMembershipRequest $request): self
    {
        return new self(
            id: $request->user->getKey(),
            name: $request->user->name,
            email: $request->user->email,
        );
    }
}
