<?php

namespace App\Http\Requests\SharedRosters;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
