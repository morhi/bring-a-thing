<?php

namespace App\Http\Requests\Polls;

use App\Enums\PollGranularity;
use App\Enums\PollType;
use App\Http\Requests\Polls\Concerns\ValidatesAnswerDeadline;
use App\Models\Group;
use App\Models\Poll;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StorePollRequest extends FormRequest
{
    use ValidatesAnswerDeadline;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Creating in a group needs its membership; a standalone poll anybody may create (F-06, F-29).
     */
    public function authorize(): bool
    {
        $group = $this->group();

        if ($group !== null) {
            return $this->user()->can('createForGroup', [Poll::class, $group]);
        }

        return $this->user()->can('create', Poll::class);
    }

    /**
     * The group the poll goes into: the route's group, or the optional group_id input of polls.store-any; null for a standalone poll.
     */
    public function group(): ?Group
    {
        $group = $this->route('group');

        if ($group instanceof Group) {
            return $group;
        }

        $groupId = $this->integer('group_id');

        return $groupId ? Group::query()->find($groupId) : null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Options are explicit day/slot rows for a date-finder poll or an
     * hour-granularity attendance poll (custom per-day slots, e.g. "lunch"
     * and "dinner"); a day-granularity attendance poll takes either the
     * days picked in the option calendar or a date range, expanded
     * server-side into one option per day (F-07).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $usesExplicitOptions = $this->usesExplicitOptions();

        return [
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
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

            ...$this->answerDeadlineRules(),
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

            $this->validateDistinctOptions($validator);
            $this->validateAutoClose($validator);
        });
    }

    /**
     * Reject option rows that repeat an earlier row's date and time (F-06).
     */
    private function validateDistinctOptions(Validator $validator): void
    {
        $seen = [];

        foreach ($this->input('options', []) as $index => $row) {
            if (! is_array($row) || empty($row['date']) || strtotime((string) $row['date']) === false) {
                continue;
            }

            $key = implode('|', [
                date('Y-m-d', (int) strtotime((string) $row['date'])),
                substr((string) ($row['starts_at'] ?? ''), 0, 5),
                substr((string) ($row['ends_at'] ?? ''), 0, 5),
            ]);

            if (isset($seen[$key])) {
                $validator->errors()->add("options.{$index}.date", __('ui.newPoll.duplicateOption'));

                continue;
            }

            $seen[$key] = true;
        }
    }

    /**
     * Whether this poll takes explicit organizer-defined option rows rather than an auto-expanded date range.
     */
    public function usesExplicitOptions(): bool
    {
        return $this->enum('type', PollType::class) === PollType::DateFinder || $this->isHourGranularity() || $this->filled('options');
    }

    /**
     * Whether this poll uses hour-level slots.
     */
    public function isHourGranularity(): bool
    {
        return $this->enum('granularity', PollGranularity::class) === PollGranularity::Hour;
    }
}
