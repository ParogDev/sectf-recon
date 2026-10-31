<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />

        <title>{{ config('app.name', 'SECTF Recon') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Match the Flux appearance setting (settings → appearance) before first paint. --}}
        <script>
            (function () {
                try {
                    var pref = localStorage.getItem('flux.appearance') || 'system';
                    var dark = pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', dark);
                } catch (e) {}
            })();
        </script>

        @php($bootstrap = [
            'appName' => config('app.name', 'SECTF Recon'),
            'user' => [
                'id' => auth()->id(),
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
                'initials' => auth()->user()->initials(),
            ],
            'urls' => [
                'settings' => route('profile.edit'),
                'logout' => route('logout'),
            ],
        ])
        <script>
            window.__SECTF__ = {{ \Illuminate\Support\Js::from($bootstrap) }};
        </script>

        @fonts

        @vite(['resources/css/spa.css', 'resources/js/spa/main.tsx'])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div id="app"></div>
    </body>
</html>
