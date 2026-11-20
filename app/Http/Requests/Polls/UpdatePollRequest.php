<?php

namespace App\Http\Requests\Polls;

use App\Enums\PollGranularity;
use App\Models\Poll;
use App\Models\PollOption;
use App\Support\WallClock;
use Carbon\CarbonInterface;
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
     * remove lists the slugs of options to delete with their responses.
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
            'remove' => ['sometimes', 'array'],
            'remove.*' => ['required', 'string', 'distinct'],
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
            if ((! $this->has('options') && ! $this->filled('remove')) || $validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->poll()->isClosed()) {
                $validator->errors()->add('options', 'This poll is closed.');

                return;
            }

            $existing = $this->poll()->options()->with('responses', 'event')->get()->keyBy('slug');

            foreach ($this->input('remove', []) as $index => $slug) {
                $this->validateRemoval($validator, $index, $existing->get($slug));
            }

            foreach ($this->input('options', []) as $index => $row) {
                $this->validateRow($validator, $index, $row, $existing->get($row['slug'] ?? ''));
            }

            $this->validateDistinctOptions($validator);
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
     * Validate that an option to remove belongs to the poll and is not fixed (F-09).
     */
    private function validateRemoval(Validator $validator, int|string $index, ?PollOption $option): void
    {
        if ($option === null) {
            $validator->errors()->add("remove.{$index}", 'This option does not belong to the poll.');

            return;
        }

        if ($option->isFixed()) {
            $validator->errors()->add("remove.{$index}", 'A fixed option cannot be removed.');
        }
    }

    /**
     * Whether the row moves the option to another date or time.
     *
     * The row's local times count in the editor's zone, so the same slot
     * re-entered from another zone keeps its instants (section 9).
     *
     * @param  array<string, mixed>  $row
     */
    private function changesSchedule(PollOption $option, array $row): bool
    {
        $schedule = PollOption::scheduleFrom([
            'date' => (string) $row['date'],
            'starts_at' => $row['starts_at'] ?? null,
            'ends_at' => $row['ends_at'] ?? null,
        ], WallClock::zoneOf($this->user()));
        $instant = fn (?CarbonInterface $value) => $value?->copy()->utc()->format('Y-m-d H:i');

        return $option->date?->format('Y-m-d') !== $schedule['date']
            || $instant($option->starts_at) !== $instant($schedule['starts_at'])
            || $instant($option->ends_at) !== $instant($schedule['ends_at']);
    }

    /**
     * The poll being edited.
     */
    private function poll(): Poll
    {
        /** @var Poll $poll */
        $poll = $this->route('poll');

        return $poll;
    }
}
