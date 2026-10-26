<?php

namespace App\Data;

use App\Models\CustomField;
use Spatie\LaravelData\Data;

class CustomFieldData extends Data
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
    ) {}

    /**
     * Build one custom field of a list (F-16).
     */
    public static function fromField(CustomField $field): self
    {
        return new self(id: $field->id, slug: $field->slug, name: $field->name);
    }
}
