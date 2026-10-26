<?php

namespace App\Http\Requests\Polls;

use App\Models\Poll;
use App\Models\PollOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

class FixPollRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('fix', $this->route('poll'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * options holds the slugs of one or more options of this poll (F-09).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'options' => ['required', 'array', 'min:1'],
            'options.*' => ['required', 'string', 'distinct'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * A closed poll accepts no fixing; already fixed options would create a second event.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->poll()->isClosed()) {
                $validator->errors()->add('options', 'This poll is closed.');

                return;
            }

            if ($this->selectedOptions()->count() !== count($this->input('options'))) {
                $validator->errors()->add('options', 'Select options of this poll.');

                return;
            }

            if ($this->selectedOptions()->contains(fn (PollOption $option) => $option->isFixed())) {
                $validator->errors()->add('options', 'An option is already fixed.');
            }
        });
    }

    /**
     * The selected options in calendar order.
     *
     * @return Collection<int, PollOption>
     */
    public function selectedOptions(): Collection
    {
        return $this->poll()->options()->with('event')->whereIn('slug', (array) $this->input('options'))->get();
    }

    /**
     * The poll being fixed.
     */
    private function poll(): Poll
    {
        return $this->route('poll');
    }
}
