<?php

namespace App\Http\Controllers;

use App\Contracts\OcrServiceInterface;
use App\Enums\VerificationStatus;
use App\Http\Requests\CheckDuplicateRequest;
use App\Http\Requests\ProcessOcrRequest;
use App\Http\Requests\StorePersonRequest;
use App\Http\Requests\UpdatePersonRequest;
use App\Models\Person;
use App\Services\CnicImageStorage;
use App\Services\CnicNormalizerService;
use App\Services\DuplicateCheckService;
use App\Services\PendingScanSession;
use App\Services\PersonService;
use App\Support\SensitiveData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonController extends Controller
{
    public function __construct(
        private readonly PersonService $persons,
        private readonly PendingScanSession $pendingScan,
        private readonly CnicImageStorage $images,
        private readonly DuplicateCheckService $duplicates,
        private readonly CnicNormalizerService $normalizer,
        private readonly OcrServiceInterface $ocr,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Person::class);

        $query = Person::query()->latest();

        if ($search = trim((string) $request->string('q'))) {
            $normalized = $this->normalizer->normalize($search);
            $query->where(function ($builder) use ($search, $normalized) {
                $builder->where('name', 'like', '%'.$search.'%');

                if ($normalized) {
                    $builder->orWhere('cnic', $normalized)->orWhere('cnic', 'like', '%'.$search.'%');
                }
            });
        }

        return view('persons.index', [
            'persons' => $query->paginate(15)->withQueryString(),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        $this->authorize('create', Person::class);

        $pending = $this->pendingScan->get();

        if ($pending === null) {
            return view('persons.scan');
        }

        return view('persons.review', [
            'pending' => $pending,
            'statuses' => VerificationStatus::cases(),
        ]);
    }

    public function ocr(ProcessOcrRequest $request): RedirectResponse
    {
        $this->pendingScan->forget();

        $frontPath = $this->images->storeTemporary($request->file('front_image'), 'front');
        $backPath = $request->file('back_image')
            ? $this->images->storeTemporary($request->file('back_image'), 'back')
            : null;

        try {
            $outcome = $this->ocr->process(
                $this->images->absolutePath($frontPath),
                $backPath ? $this->images->absolutePath($backPath) : null,
            );
        } catch (\Throwable $exception) {
            $this->images->delete($frontPath);
            $this->images->delete($backPath);

            throw $exception;
        }

        $this->pendingScan->put([
            'ocr_scan_id' => $outcome->scan->id,
            'front_image_path' => $frontPath,
            'back_image_path' => $backPath,
            'cnic' => $outcome->parsed->cnic,
            'name' => $outcome->parsed->name,
            'father_husband_name' => $outcome->parsed->fatherHusbandName,
            'address' => $outcome->parsed->address,
            'confidence' => $outcome->confidence,
            'provider' => $outcome->provider,
        ]);

        return redirect()->route('persons.create');
    }

    public function previewImage(string $side): StreamedResponse
    {
        $this->authorize('create', Person::class);

        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $pending = $this->pendingScan->get();
        $path = $pending[$side.'_image_path'] ?? null;

        abort_unless(is_string($path) && $path !== '', 404);

        $disk = Storage::disk($this->images->disk());
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function checkDuplicate(CheckDuplicateRequest $request): JsonResponse
    {
        $existing = $this->duplicates->find(
            (string) $request->validated('cnic'),
            $request->integer('ignore_person_id') ?: null,
        );

        $canView = $existing !== null && Gate::allows('view', $existing);

        return response()->json([
            'duplicate' => $existing !== null,
            'person_id' => $canView ? $existing->id : null,
            'url' => $canView ? route('persons.show', $existing) : null,
            'cnic' => $existing ? SensitiveData::maskCnic($existing->cnic) : null,
        ]);
    }

    public function store(StorePersonRequest $request): RedirectResponse
    {
        if (! $this->pendingScan->exists()) {
            return redirect()
                ->route('persons.create')
                ->withErrors(['front_image' => 'Upload a CNIC image and review the OCR result before saving.']);
        }

        $person = $this->persons->confirmAndCreate($request->user(), $request->validated());

        return redirect()
            ->route('persons.show', $person)
            ->with('status', 'CNIC record saved.');
    }

    public function discard(): RedirectResponse
    {
        $this->authorize('create', Person::class);
        $this->pendingScan->forget();

        return redirect()->route('persons.create')->with('status', 'Pending scan discarded.');
    }

    public function show(Person $person): View
    {
        $this->authorize('view', $person);

        return view('persons.show', [
            'person' => $person->load(['creator', 'updater']),
        ]);
    }

    public function edit(Person $person): View
    {
        $this->authorize('update', $person);

        return view('persons.edit', [
            'person' => $person,
            'statuses' => VerificationStatus::cases(),
        ]);
    }

    public function update(UpdatePersonRequest $request, Person $person): RedirectResponse
    {
        $person = $this->persons->update($person, $request->user(), $request->validated());

        return redirect()
            ->route('persons.show', $person)
            ->with('status', 'CNIC record updated.');
    }

    public function destroy(Person $person): RedirectResponse
    {
        $this->authorize('delete', $person);
        $this->persons->delete($person, request()->user());

        return redirect()
            ->route('persons.index')
            ->with('status', 'CNIC record deleted.');
    }
}
