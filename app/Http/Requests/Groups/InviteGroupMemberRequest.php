<?php

namespace App\Http\Requests\Groups;

use App\Models\Group;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InviteGroupMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('invite', $this->route('group'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', $this->notAlreadyInGroup()],
        ];
    }

    /**
     * Reject an email that already belongs to a member (accepted or pending) of the group.
     */
    private function notAlreadyInGroup(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            /** @var Group $group */
            $group = $this->route('group');

            if ($group->members()->where('email', $value)->exists()) {
                $fail('This person is already a member of this group, or already has an open invitation.');
            }
        };
    }
}
