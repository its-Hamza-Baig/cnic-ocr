<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OcrProviderException extends Exception
{
    public function __construct(string $message = 'The OCR provider failed to process the image.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
            ], 502);
        }

        return back()->withErrors(['front_image' => $this->getMessage()]);
    }
}
