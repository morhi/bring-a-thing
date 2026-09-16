<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestMagicLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Name is only required when the resulting account would otherwise have
     * none (a brand new email, or an existing nameless shadow account), so a
     * returning user with a name can request a magic link with email alone.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'name' => [
                Rule::requiredIf(fn () => User::emailNeedsName($this->input('email'))),
                'nullable', 'string', 'max:255',
            ],
            'roster_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
