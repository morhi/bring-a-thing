<?php

namespace App\Http\Requests\CustomFields;

use App\Models\CustomField;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomFieldRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('customField'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var CustomField $customField */
        $customField = $this->route('customField');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('custom_fields', 'name')
                    ->where('roster_id', $customField->roster_id)
                    ->ignore($customField),
            ],
        ];
    }
}
