<?php

namespace App\Http\Requests\Polls;

use App\Models\Poll;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StorePollYesToAllRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Same rule as a single answer: people outside the audience get "Not found" (F-06).
     */
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('respond', $this->route('poll'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Poll $poll */
            $poll = $this->route('poll');

            if ($poll->isClosed()) {
                $validator->errors()->add('status', 'This poll is closed.');
            }
        });
    }
}
