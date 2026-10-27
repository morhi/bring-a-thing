<?php

namespace App\Http\Requests\Search;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    /**
     * Everyone signed in searches; the results are scoped to what the account may see.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
