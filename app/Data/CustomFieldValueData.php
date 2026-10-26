<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class CustomFieldValueData extends Data
{
    public function __construct(
        public int $custom_field_id,
        public ?string $value,
    ) {}
}
