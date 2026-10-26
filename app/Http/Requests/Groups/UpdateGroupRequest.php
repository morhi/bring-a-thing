<?php

namespace App\Http\Requests\Groups;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Renaming and switching the group option are both owner-only (F-03),
     * checked separately so either ability can change on its own later.
     */
    public function authorize(): bool
    {
        $group = $this->route('group');

        if ($this->has('name') && ! $this->user()->can('update', $group)) {
            return false;
        }

        if ($this->has('members_can_set_status') && ! $this->user()->can('switchOption', $group)) {
            return false;
        }

        return $this->user()->can('update', $group) || $this->user()->can('switchOption', $group);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required_without:members_can_set_status', 'string', 'max:255'],
            'members_can_set_status' => ['sometimes', 'boolean'],
        ];
    }
}
