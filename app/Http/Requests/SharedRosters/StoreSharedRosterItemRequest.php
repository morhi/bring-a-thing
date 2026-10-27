<?php

namespace App\Http\Requests\SharedRosters;

use App\Models\Roster;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSharedRosterItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Open to anyone: SharedRosterItemController::store checks the roster's
     * own share-link add-things setting, which is the authorization
     * boundary here.
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
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'custom_fields' => ['array'],
            'custom_fields.*' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * Custom field values must belong to the shared list's own fields (F-16).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $roster = Roster::query()->where('share_token', $this->route('token'))->first();
            $validFieldIds = $roster?->customFields()->pluck('id')->all() ?? [];

            foreach (array_keys($this->input('custom_fields', [])) as $customFieldId) {
                if (! in_array((int) $customFieldId, $validFieldIds, true)) {
                    $validator->errors()->add('custom_fields', 'Invalid custom field.');
                }
            }
        });
    }
}
