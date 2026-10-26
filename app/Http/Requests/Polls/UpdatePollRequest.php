<?php

namespace App\Http\Requests\Polls;

use App\Enums\PollGranularity;
use App\Models\Poll;
use App\Models\PollOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePollRequest extends FormRequest
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
     * Type and granularity cannot change after creation (F-06), so they are
     * not read. options is optional: without it only the title changes.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isHourGranularity = $this->poll()->granularity === PollGranularity::Hour;

        return [
            'title' => ['required', 'string', 'max:255'],
            'options' => ['sometimes', 'array', 'min:1'],
            'options.*.slug' => ['nullable', 'string', 'distinct'],
            'options.*.date' => ['required', 'date'],
            'options.*.starts_at' => $isHourGranularity ? ['required', 'date_format:H:i'] : ['prohibited'],
            'options.*.ends_at' => $isHourGranularity ? ['required', 'date_format:H:i'] : ['prohibited'],
            'options.*.label' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * A closed poll allows no option changes (F-08). Options with answers
     * and fixed options keep their date and time (F-08, F-09).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->has('options') || $validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->poll()->isClosed()) {
                $validator->errors()->add('options', 'This poll is closed.');

                return;
            }

            $existing = $this->poll()->options()->with('responses', 'event')->get()->keyBy('slug');

            foreach ($this->input('options', []) as $index => $row) {
                $this->validateRow($validator, $index, $row, $existing->get($row['slug'] ?? ''));
            }
        });
    }

    /**
     * Validate one option row against its stored option, if any.
     *
     * @param  array<string, mixed>  $row
     */
    private function validateRow(Validator $validator, int|string $index, array $row, ?PollOption $option): void
    {
        if (! empty($row['starts_at']) && ! empty($row['ends_at']) && $row['ends_at'] <= $row['starts_at']) {
            $validator->errors()->add("options.{$index}.ends_at", 'The end time must be after the start time.');
        }

        if (! empty($row['slug']) && $option === null) {
            $validator->errors()->add("options.{$index}.slug", 'This option does not belong to the poll.');

            return;
        }

        if ($option === null || ! $option->hasLockedSchedule() || ! $this->changesSchedule($option, $row)) {
            return;
        }

        $validator->errors()->add(
            "options.{$index}.date",
            $option->isFixed() ? 'A fixed option keeps its date and time.' : 'An option with answers keeps its date and time.',
        );
    }

    /**
     * Whether the row moves the option to another date or time.
     *
     * @param  array<string, mixed>  $row
     */
    private function changesSchedule(PollOption $option, array $row): bool
    {
        $time = fn (?string $value) => $value === null || $value === '' ? null : substr($value, 0, 5);

        return $option->date->format('Y-m-d') !== date('Y-m-d', strtotime((string) $row['date']))
            || $time($option->starts_at) !== $time($row['starts_at'] ?? null)
            || $time($option->ends_at) !== $time($row['ends_at'] ?? null);
    }

    /**
     * The poll being edited.
     */
    private function poll(): Poll
    {
        return $this->route('poll');
    }
}
