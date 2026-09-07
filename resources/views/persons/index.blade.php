<x-layouts.app title="Persons" heading="Persons">

    <form method="GET" action="{{ route('persons.index') }}" class="mb-4 flex gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name or CNIC" class="w-full max-w-md rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Search</button>
    </form>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3">CNIC</th>
                    <th>Name</th>
                    <th>Father / Husband</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($persons as $person)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3 font-mono">{{ $person->cnic }}</td>
                        <td>{{ $person->name }}</td>
                        <td>{{ $person->father_husband_name }}</td>
                        <td class="capitalize">{{ $person->verification_status->value }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('persons.show', $person) }}" class="text-emerald-700 hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">No person records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $persons->links() }}</div>
</x-layouts.app>
