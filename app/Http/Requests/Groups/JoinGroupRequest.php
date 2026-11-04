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
    private ?Group $group = null;

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
     * Logged-in accounts join with one click. Guests always enter an email,
     * plus a name for a new account (F-04).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Resolve the group first so an unknown or revoked token is a 404 before any field error
        $this->group();

        if ($this->user() !== null) {
            return [];
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
     * An invite link requires a proven email, so a logged-in account without
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

                $validator->errors()->add('email', $this->user()->email === null
                    ? 'Add an email address in your settings to join this group.'
                    : 'Log in through the login link sent to your email to join this group.');
            },
        ];
    }

    /**
     * The group the invite link belongs to; an unknown or revoked token is "Not found".
     */
    public function group(): Group
    {
        return $this->group ??= Group::query()->where('invite_token', (string) $this->route('token'))->firstOrFail();
    }
}
