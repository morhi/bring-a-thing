<?php

namespace App\Http\Requests\RosterItems;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRosterItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('item'));
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
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
            'custom_fields' => ['array'],
            'custom_fields.*' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $validFieldIds = $this->route('roster')->customFields()->pluck('id')->all();

            foreach (array_keys($this->input('custom_fields', [])) as $customFieldId) {
                if (! in_array((int) $customFieldId, $validFieldIds, true)) {
                    $validator->errors()->add('custom_fields', 'Invalid custom field.');
                }
            }
        });
    }
}
