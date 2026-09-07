<?php

namespace App\Exceptions;

use App\Models\Person;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DuplicateCnicException extends Exception
{
    public function __construct(public readonly ?Person $existing = null)
    {
        parent::__construct('A record with this CNIC already exists.');
    }

    public function render(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'duplicate' => true,
                'person_id' => $this->existing?->id,
            ], 422);
        }

        return back()
            ->withInput($request->except(['front_image', 'back_image', 'confirmed']))
            ->with('duplicate_person_id', $this->existing?->id)
            ->withErrors(['cnic' => $this->getMessage()]);
    }
}
