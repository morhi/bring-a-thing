<?php

namespace App\Http\Requests\Friends;

use App\Models\Friend;
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
            'email' => ['required', 'email', 'max:255', $this->notOwnEmail(), $this->notAFriendYet()],
        ];
    }

    /**
     * Get the custom messages for the validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('ui.friends.add.invalid'),
            'email.email' => __('ui.friends.add.invalid'),
        ];
    }

    /**
     * Reject adding your own account as a friend.
     */
    private function notOwnEmail(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (strcasecmp($value, (string) $this->user()->email) === 0) {
                $fail(__('ui.friends.add.self'));
            }
        };
    }

    /**
     * Reject an address that already belongs to one of the user's friends.
     */
    private function notAFriendYet(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $friend = Friend::query()
                ->where('user_id', $this->user()->getKey())
                ->whereHas('friendUser', fn ($query) => $query->where('email', $value))
                ->with('friendUser')
                ->first();

            if ($friend === null) {
                return;
            }

            $fail(__('ui.friends.add.exists', ['name' => $friend->name ?? $friend->friendUser->name ?? $value]));
        };
    }
}
