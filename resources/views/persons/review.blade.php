<x-layouts.app title="Review OCR result" heading="Review OCR result">
    <div
        class="max-w-5xl"
        x-data="cnicReview({
            checkUrl: @js(route('persons.check-duplicate')),
            csrf: @js(csrf_token()),
            cnic: @js(old('cnic', $pending['cnic'])),
        })"
    >
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            These values were machine-extracted by OCR (provider: {{ $pending['provider'] ?? 'unknown' }}) and must be verified by the operator before saving. Nothing is stored as a person record until you confirm.
        </div>

        @if (session('duplicate_person_id'))
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                A record with this CNIC already exists.
                <a class="font-medium underline" href="{{ route('persons.show', session('duplicate_person_id')) }}">Open existing record</a>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[18rem_1fr]">
            <div class="space-y-4">
                <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                    <p class="border-b border-slate-100 px-4 py-2 text-sm font-medium">Front</p>
                    <img src="{{ route('persons.preview', 'front') }}" alt="CNIC front preview" class="w-full bg-slate-200 object-contain">
                </div>
                @if (!empty($pending['back_image_path']))
                    <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                        <p class="border-b border-slate-100 px-4 py-2 text-sm font-medium">Back</p>
                        <img src="{{ route('persons.preview', 'back') }}" alt="CNIC back preview" class="w-full bg-slate-200 object-contain">
                    </div>
                @endif
            </div>

            <form id="review-form" method="POST" action="{{ route('persons.store') }}" class="rounded-xl bg-white p-6 shadow-sm" @submit.prevent="submit">
                @csrf
                <input type="hidden" name="confirmed" :value="confirmed ? '1' : ''">

                <div class="space-y-4">
                    <div>
                        <label for="cnic" class="block text-sm font-medium">CNIC</label>
                        <input id="cnic" name="cnic" x-model="cnic" value="{{ old('cnic', $pending['cnic']) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono">
                        @error('cnic') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-medium">Name</label>
                        <input id="name" name="name" value="{{ old('name', $pending['name']) }}" required maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="father_husband_name" class="block text-sm font-medium">Father / Husband name</label>
                        <input id="father_husband_name" name="father_husband_name" value="{{ old('father_husband_name', $pending['father_husband_name']) }}" maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label for="address" class="block text-sm font-medium">Address</label>
                        <textarea id="address" name="address" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('address', $pending['address']) }}</textarea>
                        <p class="mt-1 text-xs text-slate-500">English and Urdu are both read. Copy a value from the image if it is empty or wrong.</p>
                    </div>
                    <div>
                        <label for="verification_status" class="block text-sm font-medium">Verification status</label>
                        <select id="verification_status" name="verification_status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(old('verification_status', 'pending') === $status->value)>{{ ucfirst($status->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <p x-cloak x-show="duplicateMessage" class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900" x-html="duplicateMessage"></p>
                @error('confirmed') <p class="mt-4 text-sm text-rose-600">{{ $message }}</p> @enderror

                <div class="mt-6 flex flex-wrap gap-3">
                    <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800" :disabled="checking">Review and save</button>
                    <button type="submit" form="discard-scan" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm">Discard scan</button>
                </div>
            </form>
        </div>

        <form id="discard-scan" method="POST" action="{{ route('persons.discard') }}" class="hidden">
            @csrf
        </form>

        <div x-cloak x-show="confirmOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold">Confirm CNIC record</h2>
                <p class="mt-2 text-sm text-slate-600">Save this record only after you have verified every field against the CNIC image. This action creates the person record.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="confirmOpen = false">Go back</button>
                    <button type="button" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm text-white" @click="confirmAndSubmit()">Confirm and save</button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
