<x-mail::message>
# {{ __('Dates fixed: :title', ['title' => $pollTitle]) }}

{{ trans_choice('The date has been fixed.|The dates have been fixed.', $events->count()) }}

@foreach ($events as $event)
- {{ $event->date->translatedFormat('l, j. F Y') }}@if ($event->starts_at !== null) {{ $event->starts_at }}@endif

@endforeach

<x-mail::button :url="$url">
{{ __('Open poll') }}
</x-mail::button>

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
