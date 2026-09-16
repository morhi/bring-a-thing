<?php

namespace App\Http\Requests\RosterItems;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UnclaimRosterItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * A member may always remove their own claim; removing another
     * member's claim additionally requires manageClaims authority.
     */
    public function authorize(): bool
    {
        $item = $this->route('item');
        $targetId = $this->integer('user_id') ?: $this->user()->getKey();

        if ($targetId === $this->user()->getKey()) {
            return $this->user()->can('claim', $item);
        }

        $target = User::find($targetId);

        return $target !== null && $this->user()->can('claimFor', [$item, $target]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
