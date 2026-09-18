<?php

namespace App\Http\Requests\RosterItems;

use App\Enums\PollType;
use App\Models\PollOption;
use App\Models\RosterItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRosterItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [RosterItem::class, $this->route('roster')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
            'attendance_poll_option_id' => ['nullable', 'integer', 'exists:poll_options,id'],
            'custom_fields' => ['array'],
            'custom_fields.*' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $validFieldIds = $this->route('roster')->customFields()->pluck('id')->all();

            foreach (array_keys($this->input('custom_fields', [])) as $customFieldId) {
                if (! in_array((int) $customFieldId, $validFieldIds, true)) {
                    $validator->errors()->add('custom_fields', 'Invalid custom field.');
                }
            }

            $optionId = $this->input('attendance_poll_option_id');

            if ($optionId === null) {
                return;
            }

            $option = PollOption::query()->with('poll')->find($optionId);
            $groupId = $this->route('roster')->group_id;

            if ($option === null || $option->poll->type !== PollType::Attendance || $option->poll->group_id !== $groupId) {
                $validator->errors()->add('attendance_poll_option_id', 'Invalid attendance day.');
            }
        });
    }
}
