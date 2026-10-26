<?php

namespace App\Http\Requests\Events;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreEventCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request: participants and managers of the event (F-20).
     */
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('comment', $this->route('event'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
