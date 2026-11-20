<?php

namespace App\Http\Requests\Events;

use App\Actions\Events\UpdateEvent;
use Illuminate\Contracts\Validation\ValidationRule;

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

    /**
     * Require the reset-or-keep choice only when the date or start time really changes (F-10).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $moved = $this->filled('date') && app(UpdateEvent::class)->hasMoved($this->route('event'), [
            'date' => $this->input('date'),
            'starts_at' => $this->input('starts_at') ?: null,
        ]);

        return [
            ...parent::rules(),
            'reset_acceptances' => $moved ? ['required', 'boolean'] : ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reset_acceptances.required' => __('ui.event.edit.chooseReset'),
            'reset_acceptances.boolean' => __('ui.event.edit.chooseReset'),
        ];
    }

    /**
     * Whether the participants' acceptances reset because the event moved; false keeps them.
     */
    public function resetsAcceptances(): bool
    {
        return $this->boolean('reset_acceptances');
    }
}
