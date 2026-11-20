<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use App\Models\Group;
use App\Support\WallClock;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $group = $this->group();
        $allowed = $group !== null
            ? $this->user()->can('createForGroup', [Event::class, $group])
            : $this->user()->can('create', Event::class);

        if (! $allowed) {
            return false;
        }

        $parent = $this->parentEvent();

        // A parent from another group fails validation instead of revealing that it exists
        return $parent === null || $this->user()->can('createSubEvent', $parent);
    }

    /**
     * The group the event goes into: the route's group, or the optional group_id input of events.store-any; null for a standalone event (F-10, F-29).
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
     * The parent event of a sub-event (F-10, F-12), when it exists in the same group, or is standalone for a standalone sub-event.
     *
     * A parent the user may not open counts as missing, so another person's
     * standalone event fails like an unknown id (section 8).
     */
    public function parentEvent(): ?Event
    {
        if (! $this->filled('parent_event_id') || ! is_numeric($this->input('parent_event_id'))) {
            return null;
        }

        $parent = Event::query()
            ->where(fn (EloquentBuilder $query) => $this->constrainToGroup($query))
            ->find((int) $this->input('parent_event_id'));

        if ($parent === null || ! $this->user()->can('view', $parent)) {
            return null;
        }

        return $parent;
    }

    /**
     * Limit a query on events to the request's group, or to standalone events without one.
     *
     * @param  EloquentBuilder<Event>|QueryBuilder  $query
     */
    private function constrainToGroup(EloquentBuilder|QueryBuilder $query): void
    {
        $group = $this->group();

        if ($group === null) {
            $query->whereNull('group_id');

            return;
        }

        $query->where('group_id', $group->getKey());
    }

    /**
     * Default an empty end date to the start date, so every event has one (F-10).
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('end_date') && $this->filled('date')) {
            $this->merge(['end_date' => $this->input('date')]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The start is a date with an optional time; the end date is required
     * (it defaults to the start date), its time is optional (F-10).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->parentRules(),
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:date'],
            'ends_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * The rules for the optional group and parent of a sub-event; the parent must lie in the same group, or be standalone too (F-10, F-12, F-29).
     *
     * @return array<string, array<mixed>>
     */
    protected function parentRules(): array
    {
        return [
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'parent_event_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists('events', 'id')->where(fn (QueryBuilder $query) => $this->constrainToGroup($query)),
                fn (string $attribute, mixed $value, Closure $fail) => $this->parentEvent() === null ? $fail('validation.exists')->translate() : null,
            ],
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
     * The validated event attributes, with empty optional fields as null and times as UTC instants.
     *
     * @return array{title: string, description: string|null, location: string|null, date: string|null, starts_at: Carbon|null, end_date: string|null, ends_at: Carbon|null}
     */
    public function eventAttributes(): array
    {
        /** @var array{title: string, description: string|null, location: string|null, date: string, starts_at: string|null, end_date: string|null, ends_at: string|null} $attributes */
        $attributes = $this->safe()->only(['title', 'description', 'location', 'date', 'starts_at', 'end_date', 'ends_at']) + [
            'description' => null,
            'location' => null,
            'starts_at' => null,
            'end_date' => null,
            'ends_at' => null,
        ];

        return [...$attributes, ...$this->schedule($attributes)];
    }

    /**
     * Map the local start and end inputs to the stored columns in the acting person's zone (section 9).
     *
     * Each side keeps a plain date without a time, or becomes a UTC instant with one.
     *
     * @param  array{date: string, starts_at?: string|null, end_date?: string|null, ends_at?: string|null}  $input
     * @return array{date: string|null, starts_at: Carbon|null, end_date: string|null, ends_at: Carbon|null}
     */
    protected function schedule(array $input): array
    {
        $zone = WallClock::zoneOf($this->user());
        [$date, $startsAt] = WallClock::toStored($input['date'], $input['starts_at'] ?? null, $zone);
        [$endDate, $endsAt] = WallClock::toStored($input['end_date'] ?? $input['date'], $input['ends_at'] ?? null, $zone);

        return ['date' => $date, 'starts_at' => $startsAt, 'end_date' => $endDate, 'ends_at' => $endsAt];
    }
}
