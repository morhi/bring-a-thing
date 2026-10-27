<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SearchResultData extends Data
{
    /**
     * One record in the command palette (F-25).
     *
     * `context` is a ready-made line such as the group name or the event
     * date, so the client renders it as is.
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $context,
        public string $url,
    ) {}
}
