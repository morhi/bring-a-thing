<x-mail::message>
# Log in to {{ config('app.name') }}

Click the button below to log in. This link expires in {{ $expiresInMinutes }} minutes.

<x-mail::button :url="$url">
Log in
</x-mail::button>

If you did not request this, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
