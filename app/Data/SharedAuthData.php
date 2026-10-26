<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SharedAuthData extends Data
{
    public function __construct(
        public ?SharedUserData $user,
    ) {}
}
