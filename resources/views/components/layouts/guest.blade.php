@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ $title ?? 'Sign in' }} — {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="mx-auto flex min-h-screen max-w-md items-center px-4">
            <div class="w-full rounded-2xl border border-slate-800 bg-slate-900 p-8 shadow-xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
