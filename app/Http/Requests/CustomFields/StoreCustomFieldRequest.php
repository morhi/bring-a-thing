<?php

namespace App\Http\Requests\CustomFields;

use App\Models\CustomField;
use App\Models\Roster;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomFieldRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [CustomField::class, $this->route('roster')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Roster $roster */
        $roster = $this->route('roster');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('custom_fields', 'name')->where('roster_id', $roster->id),
            ],
        ];
    }
}
