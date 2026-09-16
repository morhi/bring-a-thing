<?php

namespace App\Http\Requests\Polls;

use App\Enums\PollGranularity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePollOptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('poll'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Start/end times are required for an hour-granularity poll and
     * disallowed otherwise, matching StorePollRequest's per-row rules.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isHourGranularity = $this->route('poll')->granularity === PollGranularity::Hour;

        return [
            'date' => ['required', 'date'],
            'starts_at' => $isHourGranularity ? ['required', 'date_format:H:i'] : ['prohibited'],
            'ends_at' => $isHourGranularity ? ['required', 'date_format:H:i'] : ['prohibited'],
            'label' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! empty($this->input('starts_at')) && ! empty($this->input('ends_at')) && $this->input('ends_at') <= $this->input('starts_at')) {
                $validator->errors()->add('ends_at', 'The end time must be after the start time.');
            }

            if ($this->route('poll')->isClosed()) {
                $validator->errors()->add('date', 'This poll is closed.');
            }
        });
    }
}
