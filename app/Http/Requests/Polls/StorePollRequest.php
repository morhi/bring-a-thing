<?php

namespace App\Http\Requests\Polls;

use App\Enums\PollGranularity;
use App\Enums\PollType;
use App\Models\Poll;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StorePollRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('createForGroup', [Poll::class, $this->route('group')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Options are explicit day/slot rows for a date-finder poll or an
     * hour-granularity attendance poll (custom per-day slots, e.g. "lunch"
     * and "dinner"); a day-granularity attendance poll instead takes a date
     * range, expanded server-side into one option per day.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $usesExplicitOptions = $this->usesExplicitOptions();

        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(PollType::class)],
            'granularity' => ['required', new Enum(PollGranularity::class)],

            'options' => [Rule::requiredIf($usesExplicitOptions), 'array', 'min:1'],
            'options.*.date' => ['required', 'date'],
            'options.*.starts_at' => $this->isHourGranularity() ? ['required', 'date_format:H:i'] : ['prohibited'],
            'options.*.ends_at' => $this->isHourGranularity() ? ['required', 'date_format:H:i'] : ['prohibited'],
            'options.*.label' => ['nullable', 'string', 'max:100'],

            'starts_on' => [Rule::requiredIf(! $usesExplicitOptions), 'date'],
            'ends_on' => [Rule::requiredIf(! $usesExplicitOptions), 'date', 'after_or_equal:starts_on'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('options', []) as $index => $option) {
                if (! empty($option['starts_at']) && ! empty($option['ends_at']) && $option['ends_at'] <= $option['starts_at']) {
                    $validator->errors()->add("options.{$index}.ends_at", 'The end time must be after the start time.');
                }
            }
        });
    }

    /**
     * Whether this poll takes explicit organizer-defined option rows rather than an auto-expanded date range.
     */
    public function usesExplicitOptions(): bool
    {
        return $this->enum('type', PollType::class) === PollType::DateFinder || $this->isHourGranularity();
    }

    /**
     * Whether this poll uses hour-level slots.
     */
    public function isHourGranularity(): bool
    {
        return $this->enum('granularity', PollGranularity::class) === PollGranularity::Hour;
    }
}
