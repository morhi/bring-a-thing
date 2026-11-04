<?php

namespace App\Http\Requests\Events;

class UpdateEventRequest extends StoreEventRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }

    /**
     * An event keeps its parent when edited, so the update has no parent rule.
     *
     * @return array<string, array<mixed>>
     */
    protected function parentRules(): array
    {
        return [];
    }
}
