<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Notifications\DatabaseNotification;

class MarkNotificationReadRequest extends FormRequest
{
    /**
     * Everyone signed in marks their own notifications; notification() scopes the lookup to the account.
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
        return [];
    }

    /**
     * The addressed notification of the signed-in account.
     *
     * Another account's notification answers "Not found", so its existence stays hidden (section 8).
     */
    public function notification(): DatabaseNotification
    {
        return $this->user()->notifications()->findOrFail((string) $this->route('notification'));
    }
}
