<x-layouts.app title="Scan CNIC" heading="Scan CNIC">

    <div class="max-w-2xl rounded-xl bg-white p-6 shadow-sm">
        <p class="text-sm text-slate-600">Upload the CNIC front image. The back image is optional. Images are stored privately and are never published.</p>

        <form method="POST" action="{{ route('persons.ocr') }}" enctype="multipart/form-data" class="mt-6 space-y-5">
            @csrf
            <div>
                <label for="front_image" class="block text-sm font-medium">Front image</label>
                <input id="front_image" name="front_image" type="file" accept="image/jpeg,image/png" required class="mt-2 block w-full text-sm">
                @error('front_image') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="back_image" class="block text-sm font-medium">Back image (optional)</label>
                <input id="back_image" name="back_image" type="file" accept="image/jpeg,image/png" class="mt-2 block w-full text-sm">
                @error('back_image') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">Extract details</button>
        </form>
    </div>
</x-layouts.app>
