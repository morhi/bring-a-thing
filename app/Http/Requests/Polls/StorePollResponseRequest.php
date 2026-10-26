<?php

namespace App\Http\Requests\Polls;

use App\Enums\PollResponseStatus;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StorePollResponseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * People outside the poll's audience get "Not found" (section 8, F-12).
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
        return [
            'status' => ['required', new Enum(PollResponseStatus::class)],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->route('poll')->isClosed()) {
                $validator->errors()->add('status', 'This poll is closed.');
            }
        });
    }
}
