<?php

namespace App\Http\Requests\Events;

use App\Enums\ParticipationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateOwnParticipationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Goes through the gate so people outside the group get "Not found"
     * and a past event its own message.
     */
    public function authorize(): bool
    {
        return Gate::authorize('respond', $this->route('event'))->allowed();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * A person accepts or declines for themselves; "pending" is only set by
     * others or by moving the event. confirm_release confirms that a decline
     * releases the person's claims (F-11).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ParticipationStatus::class)->only([ParticipationStatus::Accepted, ParticipationStatus::Declined])],
            'confirm_release' => ['sometimes', 'boolean'],
        ];
    }
}
