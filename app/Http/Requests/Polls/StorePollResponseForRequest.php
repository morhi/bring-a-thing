<?php

namespace App\Http\Requests\Polls;

use App\Enums\PollResponseStatus;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StorePollResponseForRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only the poll's editors may, and never on a closed poll (F-06).
     */
    public function authorize(): bool
    {
        return $this->user()->can('editAnswers', $this->route('poll'));
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
     * Get the "after" validation callables for the request.
     *
     * Answers can only be set for the current audience, so people who
     * withdrew or did not accept the source event stay out (F-08, F-12).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Poll $poll */
                $poll = $this->route('poll');
                /** @var User $member */
                $member = $this->route('member');

                if (! $poll->audience()->contains('id', $member->getKey())) {
                    $validator->errors()->add('status', __('ui.poll.answer.notInAudience'));
                }
            },
        ];
    }
}
