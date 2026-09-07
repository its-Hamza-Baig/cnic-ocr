<x-layouts.app title="Edit person" heading="Edit person">

    @if (session('duplicate_person_id'))
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            A record with this CNIC already exists.
            <a class="font-medium underline" href="{{ route('persons.show', session('duplicate_person_id')) }}">Open existing record</a>
        </div>
    @endif

    <form method="POST" action="{{ route('persons.update', $person) }}" class="max-w-2xl rounded-xl bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        <div class="space-y-4">
            <div>
                <label for="cnic" class="block text-sm font-medium">CNIC</label>
                <input id="cnic" name="cnic" value="{{ old('cnic', $person->cnic) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono">
                @error('cnic') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="name" class="block text-sm font-medium">Name</label>
                <input id="name" name="name" value="{{ old('name', $person->name) }}" required maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="father_husband_name" class="block text-sm font-medium">Father / Husband name</label>
                <input id="father_husband_name" name="father_husband_name" value="{{ old('father_husband_name', $person->father_husband_name) }}" maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>
            <div>
                <label for="address" class="block text-sm font-medium">Address</label>
                <textarea id="address" name="address" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('address', $person->address) }}</textarea>
            </div>
            <div>
                <label for="verification_status" class="block text-sm font-medium">Verification status</label>
                <select id="verification_status" name="verification_status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(old('verification_status', $person->verification_status->value) === $status->value)>{{ ucfirst($status->value) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-6 flex gap-3">
            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white">Save changes</button>
            <a href="{{ route('persons.show', $person) }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm">Cancel</a>
        </div>
    </form>
</x-layouts.app>
