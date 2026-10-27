<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <meta name="theme-color" content="#047857">

        @php($description = __('Find a date, fix it and share who brings what. Polls, events and lists for bands, clubs and friends in one place.'))
        <meta name="description" content="{{ $description }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ config('app.name') }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ asset('og-image.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ config('app.name') }}">
        <meta name="twitter:card" content="summary_large_image">

        {{-- Apply the system color scheme before first paint so dark mode does not flash --}}
        <script>
            (function () {
                // Same key and values as use-theme.ts; storage can be blocked, then the system preference applies
                let stored = null;

                try {
                    stored = window.localStorage.getItem('bat.theme');
                } catch (e) {}

                const appearance = stored === 'light' || stored === 'dark' ? stored : '{{ $appearance ?? "system" }}';

                if (appearance === 'dark') {
                    document.documentElement.classList.add('dark');

                    return;
                }

                if (appearance === 'light') {
                    return;
                }

                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body>
        <x-inertia::app />
    </body>
</html>
