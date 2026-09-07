<?php

namespace App\Services\Ocr\Providers;

use App\Contracts\OcrProviderInterface;
use App\DTOs\OcrProviderResult;
use App\Exceptions\OcrProviderException;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleVisionOcrProvider implements OcrProviderInterface
{
    public function name(): string
    {
        return 'google_vision';
    }

    public function recognize(string $absoluteImagePath, string $side = 'front'): OcrProviderResult
    {
        $apiKey = config('ocr.google_vision.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new OcrProviderException('Google Vision is not configured.');
        }

        $contents = @file_get_contents($absoluteImagePath);

        if ($contents === false) {
            throw new OcrProviderException('The CNIC image could not be read for OCR.');
        }

        try {
            $response = Http::timeout((int) config('ocr.timeout_seconds', 30))
                ->asJson()
                ->post((string) config('ocr.google_vision.endpoint').'?key='.urlencode($apiKey), [
                    'requests' => [[
                        'image' => [
                            'content' => base64_encode($contents),
                        ],
                        'features' => [[
                            'type' => 'DOCUMENT_TEXT_DETECTION',
                        ]],
                    ]],
                ]);
        } catch (Throwable) {
            throw new OcrProviderException;
        }

        if ($response->failed()) {
            throw new OcrProviderException;
        }

        $text = data_get($response->json(), 'responses.0.fullTextAnnotation.text');
        $confidence = data_get($response->json(), 'responses.0.fullTextAnnotation.pages.0.confidence');

        return new OcrProviderResult(
            text: is_string($text) ? $text : '',
            confidence: is_numeric($confidence) ? round((float) $confidence, 2) : null,
            provider: $this->name(),
        );
    }
}
