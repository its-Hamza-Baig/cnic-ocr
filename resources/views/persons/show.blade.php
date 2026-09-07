<x-layouts.app title="{{ $person->name }}" heading="Person record">

    <div class="grid gap-6 lg:grid-cols-[20rem_1fr]">
        <div class="space-y-4">
            @if ($person->front_image_path)
                <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                    <p class="border-b border-slate-100 px-4 py-2 text-sm font-medium">Front image</p>
                    <img src="{{ route('persons.image', [$person, 'front']) }}" alt="CNIC front" class="w-full object-contain">
                </div>
            @endif
            @if ($person->back_image_path)
                <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                    <p class="border-b border-slate-100 px-4 py-2 text-sm font-medium">Back image</p>
                    <img src="{{ route('persons.image', [$person, 'back']) }}" alt="CNIC back" class="w-full object-contain">
                </div>
            @endif
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-slate-500">CNIC</dt>
                    <dd class="mt-1 font-mono text-lg">{{ $person->cnic }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Verification</dt>
                    <dd class="mt-1 capitalize">{{ $person->verification_status->value }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm text-slate-500">Name</dt>
                    <dd class="mt-1">{{ $person->name }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm text-slate-500">Father / Husband name</dt>
                    <dd class="mt-1">{{ $person->father_husband_name ?: '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm text-slate-500">Address</dt>
                    <dd class="mt-1 whitespace-pre-wrap">{{ $person->address ?: '—' }}</dd>
                </div>
            </dl>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('persons.edit', $person) }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Edit</a>
                <form method="POST" action="{{ route('persons.destroy', $person) }}" onsubmit="return confirm('Delete this CNIC record?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg border border-rose-300 px-4 py-2 text-sm text-rose-700">Delete</button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
