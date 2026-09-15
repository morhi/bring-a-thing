<?php

namespace App\Http\Requests\Rosters;

use App\Models\Group;
use App\Models\Roster;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRosterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $group = $this->group();

        if ($group) {
            return $this->user()->can('createForGroup', [Roster::class, $group]);
        }

        return $this->user()->can('create', Roster::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
        ];
    }

    /**
     * The group the roster should be attached to, if any.
     */
    public function group(): ?Group
    {
        $groupId = $this->integer('group_id');

        return $groupId ? Group::query()->find($groupId) : null;
    }
}
