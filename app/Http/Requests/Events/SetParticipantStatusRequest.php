<?php

namespace App\Http\Requests\Events;

use App\Enums\ParticipationStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SetParticipantStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('setStatusForOthers', $this->route('event'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ParticipationStatus::class)],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * The status can only be set for current members of the event's group.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Event $event */
                $event = $this->route('event');
                /** @var User $member */
                $member = $this->route('member');

                if (! $event->group->hasMember($member)) {
                    $validator->errors()->add('status', 'This person is not a member of the group.');
                }
            },
        ];
    }
}
