<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('createForGroup', [Event::class, $this->route('group')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The start is a date with an optional time; the optional end needs a
     * date, and its time is optional too (F-10).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'end_date' => ['nullable', 'required_with:ends_at', 'date_format:Y-m-d', 'after_or_equal:date'],
            'ends_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * An end on the start day must not lie before the start time.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->input('end_date') !== $this->input('date')) {
                    return;
                }

                if (! $this->filled('starts_at') || ! $this->filled('ends_at')) {
                    return;
                }

                if ($this->input('ends_at') <= $this->input('starts_at')) {
                    $validator->errors()->add('ends_at', 'The end must be after the start.');
                }
            },
        ];
    }

    /**
     * The validated event attributes, with empty optional fields as null.
     *
     * @return array<string, mixed>
     */
    public function eventAttributes(): array
    {
        return $this->safe()->only(['title', 'description', 'location', 'date', 'starts_at', 'end_date', 'ends_at']) + [
            'description' => null,
            'location' => null,
            'starts_at' => null,
            'end_date' => null,
            'ends_at' => null,
        ];
    }
}
