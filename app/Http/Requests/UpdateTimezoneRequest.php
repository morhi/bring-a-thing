<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTimezoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Browser detection sends detected=true and is never rejected: the controller
     * falls back to Europe/Zurich for a zone it cannot use.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->boolean('detected')) {
            return ['detected' => ['boolean']];
        }

        return [
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }
}
