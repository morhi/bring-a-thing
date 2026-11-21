<?php

namespace App\Http\Requests\Polls;

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollResponse;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StorePollResponseNoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * A note follows the answer's audience rule: people outside get "Not found" (F-06, F-12).
     */
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('respond', $this->route('poll'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * An empty note clears it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:'.PollResponse::NOTE_MAX_LENGTH],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Poll $poll */
            $poll = $this->route('poll');
            /** @var PollOption $option */
            $option = $this->route('option');

            if ($poll->isClosed()) {
                $validator->errors()->add('note', __('ui.poll.note.closed'));

                return;
            }

            $hasAnswer = $option->responses()->where('user_id', $this->user()->getKey())->exists();

            if (! $hasAnswer) {
                $validator->errors()->add('note', __('ui.poll.note.needsAnswer'));
            }
        });
    }
}
