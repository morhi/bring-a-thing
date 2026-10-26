<x-mail::message>
# {{ __('Confirm your email address for :app', ['app' => config('app.name')]) }}

{{ __('Click the button below to confirm this email address. This link expires in :minutes minutes.', ['minutes' => $expiresInMinutes]) }}

<x-mail::button :url="$url">
{{ __('Confirm email') }}
</x-mail::button>

{{ __('If you did not request this, you can safely ignore this email.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
