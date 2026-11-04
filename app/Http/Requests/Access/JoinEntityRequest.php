<?php

namespace App\Http\Requests\Access;

use App\Models\Event;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JoinEntityRequest extends FormRequest
{
    private Event|Poll|null $entity = null;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Holding the invite link is the authorization; guests may join.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Logged-in accounts join with one click. A guest joins with a name
     * only, or with an email (plus a name for a new account) and a login
     * link (F-26, F-27).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Resolve the entity first so an unknown or revoked token is a 404 before any field error
        $this->entity();

        if ($this->user() !== null) {
            return [];
        }

        return [
            'email' => ['nullable', 'email', 'max:255'],
            'name' => [
                Rule::requiredIf(fn () => ! $this->filled('email') || User::emailNeedsName($this->input('email'))),
                'nullable', 'string', 'max:255',
            ],
        ];
    }

    /**
     * The event or poll the invite link belongs to; an unknown or revoked token is "Not found".
     */
    public function entity(): Event|Poll
    {
        $model = $this->routeIs('polls.join.*') ? Poll::class : Event::class;

        return $this->entity ??= $model::query()->where('invite_token', (string) $this->route('token'))->firstOrFail();
    }
}
