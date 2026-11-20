<?php

namespace App\Http\Requests\Events;

use App\Actions\Events\UpdateEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

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
        $moved = $this->hasValidStart() && app(UpdateEvent::class)->hasMoved($this->route('event'), Arr::only($this->schedule([
            'date' => $this->string('date')->value(),
            'starts_at' => $this->input('starts_at') ?: null,
        ]), ['date', 'starts_at']));

        return [
            ...parent::rules(),
            'reset_acceptances' => $moved ? ['required', 'boolean'] : ['nullable'],
        ];
    }

    /**
     * Whether the start inputs can be read before validation; invalid ones fail validation anyway.
     */
    private function hasValidStart(): bool
    {
        return Validator::make($this->only(['date', 'starts_at']), [
            'date' => ['required', 'date_format:Y-m-d'],
            'starts_at' => ['nullable', 'date_format:H:i'],
        ])->passes();
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
