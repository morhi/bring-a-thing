<x-mail::message>
# {{ __('Log in to :app', ['app' => config('app.name')]) }}

{{ __('Click the button below to log in. This link expires in :minutes minutes.', ['minutes' => $expiresInMinutes]) }}

<x-mail::button :url="$url">
{{ __('Log in') }}
</x-mail::button>

{{ __('If you did not request this, you can safely ignore this email.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
