<?php

namespace App\Http\Requests\Groups;

use App\Models\Group;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class JoinGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Holding the invite link is the authorization; guests may join.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Logged-in accounts join with one click. Guests enter a name on an open
     * link, or an email (plus a name for a new account) on a closed link (F-04).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->user() !== null) {
            return [];
        }

        if (! $this->group()->invite_link_locked) {
            return [
                'name' => ['required', 'string', 'max:255'],
            ];
        }

        return [
            'email' => ['required', 'email', 'max:255'],
            'name' => [
                Rule::requiredIf(fn () => User::emailNeedsName($this->input('email'))),
                'nullable', 'string', 'max:255',
            ],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * A closed link requires a proven email, so a logged-in account without
     * a verified email has to add or confirm one first (F-01).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->user() === null || $this->user()->hasVerifiedEmail()) {
                    return;
                }

                if (! $this->group()->invite_link_locked) {
                    return;
                }

                $validator->errors()->add('email', $this->user()->email === null
                    ? 'Add an email address in your settings to join this group.'
                    : 'Log in through the login link sent to your email to join this group.');
            },
        ];
    }

    /**
     * The group the invite link belongs to.
     */
    public function group(): Group
    {
        /** @var Group */
        return $this->route('group');
    }
}
