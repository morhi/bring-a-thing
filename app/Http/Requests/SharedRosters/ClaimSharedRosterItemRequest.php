<?php

namespace App\Http\Requests\SharedRosters;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ClaimSharedRosterItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Open to anyone: the route itself only resolves for a roster with an
     * active share token, which is the authorization boundary here.
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
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
