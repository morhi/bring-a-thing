<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddAccountEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only name-only accounts add an email (F-01); changing an existing email is not offered.
     */
    public function authorize(): bool
    {
        return $this->user()->email === null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * No uniqueness rule: telling taken addresses apart would let a
     * name-only account probe which emails are registered. The controller
     * answers the same either way and confirmation re-checks uniqueness.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
