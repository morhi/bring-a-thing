<?php

namespace App\Http\Requests\Polls;

use App\Enums\PollType;
use App\Models\Poll;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * A date-finder poll must be closed with one of its own options chosen
     * as the result; an attendance poll has no single winner and takes no
     * option_id.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Poll $poll */
        $poll = $this->route('poll');
        $isDateFinder = $poll->type === PollType::DateFinder;

        return [
            'option_id' => $isDateFinder
                ? ['required', Rule::exists('poll_options', 'id')->where('poll_id', $poll->getKey())]
                : ['prohibited'],
        ];
    }
}
