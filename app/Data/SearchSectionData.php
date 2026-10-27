<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SearchSectionData extends Data
{
    /**
     * One record type in the command palette with its translated heading (F-25).
     *
     * @param  array<int, SearchResultData>  $results
     */
    public function __construct(
        public string $type,
        public string $label,
        public array $results,
    ) {}
}
