<?php

namespace App\Http\Requests\Polls;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StorePollCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request: everyone who may view the poll (F-20).
     */
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('comment', $this->route('poll'));
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
