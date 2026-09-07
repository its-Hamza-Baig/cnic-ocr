<x-layouts.app title="Dashboard" heading="Dashboard">

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Persons</p>
            <p class="mt-2 text-3xl font-semibold">{{ $personCount }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Pending verification</p>
            <p class="mt-2 text-3xl font-semibold">{{ $pendingCount }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Verified</p>
            <p class="mt-2 text-3xl font-semibold">{{ $verifiedCount }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Failed OCR scans</p>
            <p class="mt-2 text-3xl font-semibold">{{ $failedOcrCount }}</p>
        </div>
    </div>

    <section class="mt-8 rounded-xl bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold">Recent records</h2>
            <a href="{{ route('persons.index') }}" class="text-sm text-emerald-700 hover:underline">View all</a>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-slate-500">
                    <tr>
                        <th class="py-2">CNIC</th>
                        <th>Name</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentPersons as $person)
                        <tr class="border-t border-slate-100">
                            <td class="py-3 font-mono">{{ $person->cnic }}</td>
                            <td>{{ $person->name }}</td>
                            <td class="capitalize">{{ $person->verification_status->value }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-6 text-slate-500">No records yet. Scan a CNIC to get started.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
