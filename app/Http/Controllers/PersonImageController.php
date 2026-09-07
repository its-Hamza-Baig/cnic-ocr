<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Services\CnicImageStorage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonImageController extends Controller
{
    public function __invoke(Person $person, string $side, CnicImageStorage $images): StreamedResponse
    {
        $this->authorize('view', $person);
        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $path = $side === 'front' ? $person->front_image_path : $person->back_image_path;
        abort_unless(is_string($path) && $path !== '', 404);

        $disk = Storage::disk($images->disk());
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
