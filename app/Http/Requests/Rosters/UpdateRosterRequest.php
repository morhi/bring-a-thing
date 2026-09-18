<?php

namespace App\Http\Requests\Rosters;

use App\Enums\PollType;
use App\Models\PollOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRosterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('roster'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
            'members_can_add_items' => ['boolean'],
            'attendance_poll_option_id' => ['nullable', 'integer', 'exists:poll_options,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
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
