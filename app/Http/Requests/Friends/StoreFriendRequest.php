<?php

namespace App\Http\Requests\Friends;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFriendRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', $this->notOwnEmail()],
        ];
    }

    /**
     * Reject adding your own account as a friend.
     */
    private function notOwnEmail(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (strcasecmp($value, $this->user()->email) === 0) {
                $fail('You cannot add yourself as a friend.');
            }
        };
    }
}
