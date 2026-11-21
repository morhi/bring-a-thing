<?php

namespace App\Http\Requests\Polls\Concerns;

use App\Support\WallClock;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Validate and read the poll editor's optional answer deadline with auto-close (F-08).
 */
trait ValidatesAnswerDeadline
{
    /**
     * The rules for the deadline's local date and time, entered in the editor's zone (section 9).
     *
     * @return array<string, array<int, string>>
     */
    protected function answerDeadlineRules(): array
    {
        return [
            'deadline_date' => ['nullable', 'date', 'required_with:deadline_time'],
            'deadline_time' => ['nullable', 'date_format:H:i', 'required_with:deadline_date'],
            'auto_close' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Reject auto-close without a deadline, since there is nothing to close at.
     */
    protected function validateAutoClose(Validator $validator): void
    {
        if (! $this->boolean('auto_close') || $this->filled('deadline_date')) {
            return;
        }

        $validator->errors()->add('auto_close', __('ui.newPoll.deadline.autoCloseNeedsDeadline'));
    }

    /**
     * Whether the request carries the deadline fields, so a plain rename keeps the stored deadline.
     */
    public function hasAnswerDeadlineInput(): bool
    {
        return $this->has('deadline_date');
    }

    /**
     * The deadline as a UTC instant, or null without one (section 9).
     */
    public function answerDeadline(): ?Carbon
    {
        if (! $this->filled('deadline_date') || ! $this->filled('deadline_time')) {
            return null;
        }

        return WallClock::instant($this->string('deadline_date')->value(), $this->string('deadline_time')->value(), WallClock::zoneOf($this->user()));
    }
}
