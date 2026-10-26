<?php

namespace App\Http\Requests\Groups;

use App\Models\Group;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Group::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Invitations are optional and can be done later (F-03, F-04).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'invites' => ['nullable', 'array', 'max:50'],
            'invites.*.email' => ['required', 'email', 'max:255', 'distinct:ignore_case', $this->notOwnEmail()],
            'invites.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Reject the creator's own email, who joins as owner anyway.
     */
    private function notOwnEmail(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (mb_strtolower((string) $value) !== mb_strtolower((string) $this->user()->email)) {
                return;
            }

            $fail('You are part of the group already.');
        };
    }
}
