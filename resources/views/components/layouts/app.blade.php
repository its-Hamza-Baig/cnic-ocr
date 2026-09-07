@props(['title' => null, 'heading' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        <div class="flex min-h-screen">
            <aside class="hidden w-64 shrink-0 bg-slate-900 text-slate-100 lg:flex lg:flex-col">
                <div class="border-b border-slate-800 px-6 py-5">
                    <p class="text-xs uppercase tracking-widest text-slate-400">CNIC Management</p>
                    <p class="mt-1 text-lg font-semibold">Phase 1 MVP</p>
                </div>
                <nav class="flex flex-1 flex-col gap-1 p-4 text-sm">
                    <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('dashboard') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Dashboard</a>
                    <a href="{{ route('persons.create') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('persons.create') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Scan CNIC</a>
                    <a href="{{ route('persons.index') }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('persons.index') || request()->routeIs('persons.show') || request()->routeIs('persons.edit') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Persons</a>
                </nav>
                <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-800 p-4">
                    @csrf
                    <p class="truncate text-sm text-slate-300">{{ auth()->user()->name }}</p>
                    <button type="submit" class="mt-3 text-sm text-slate-400 hover:text-white">Log out</button>
                </form>
            </aside>
            <div class="flex min-w-0 flex-1 flex-col">
                <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 lg:px-8">
                    <h1 class="text-lg font-semibold">{{ $heading ?? $title ?? 'Dashboard' }}</h1>
                    <a href="{{ route('persons.create') }}" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800">Scan CNIC</a>
                </header>
                <main class="flex-1 px-4 py-6 lg:px-8">
                    @if (session('status'))
                        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
                    @endif
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
