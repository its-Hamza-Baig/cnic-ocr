<x-layouts.guest title="Sign in">

    <p class="text-xs uppercase tracking-widest text-slate-400">CNIC Management</p>
    <h1 class="mt-2 text-2xl font-semibold text-white">Sign in</h1>
    <p class="mt-2 text-sm text-slate-400">Access is restricted to authorized operators.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm text-slate-300">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
            @error('email') <p class="mt-1 text-sm text-rose-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="block text-sm text-slate-300">Password</label>
            <input id="password" name="password" type="password" required class="mt-1 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="remember" value="1" class="rounded border-slate-600">
            Remember me
        </label>
        <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 font-medium text-white hover:bg-emerald-500">Sign in</button>
    </form>
</x-layouts.guest>
