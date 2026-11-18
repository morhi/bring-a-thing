<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class OperatorData extends Data
{
    public function __construct(
        public ?string $name,
        public ?string $address,
        public ?string $email,
    ) {}

    /**
     * Build the operator identity from the legal config (F-33).
     */
    public static function fromConfig(): self
    {
        return new self(
            name: config('legal.name'),
            address: config('legal.address'),
            email: config('legal.email'),
        );
    }
}
