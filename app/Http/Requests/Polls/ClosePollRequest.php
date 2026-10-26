<?php

namespace App\Http\Requests\Polls;

use App\Models\Poll;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ClosePollRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('poll'));
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
     *
     * A date finder poll closes by fixing dates (F-08), unless it already has a fixed option.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Poll $poll */
            $poll = $this->route('poll');

            if ($poll->canCloseWithoutFixing()) {
                return;
            }

            $validator->errors()->add('poll', 'A date finder poll closes by fixing dates.');
        });
    }
}
