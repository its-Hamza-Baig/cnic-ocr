<?php

namespace App\Services\Ocr\Providers;

use App\Contracts\OcrProviderInterface;
use App\DTOs\OcrProviderResult;
use App\Exceptions\OcrProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class PaddleOcrProvider implements OcrProviderInterface
{
    public function name(): string
    {
        return 'paddleocr';
    }

    public function recognize(string $absoluteImagePath, string $side = 'front'): OcrProviderResult
    {
        $contents = @file_get_contents($absoluteImagePath);

        if ($contents === false) {
            throw new OcrProviderException('The CNIC image could not be read for OCR.');
        }

        $baseUrl = rtrim((string) config('ocr.paddleocr.url', 'http://127.0.0.1:8868'), '/');
        $langs = (string) config('ocr.paddleocr.langs', 'en,ar');
        $timeout = max(30, (int) config('ocr.timeout_seconds', 180));

        try {
            $response = Http::timeout($timeout)
                ->withBody($contents, $this->mime($absoluteImagePath))
                ->post($baseUrl.'/ocr?'.http_build_query(['langs' => $langs]));
        } catch (ConnectionException) {
            throw new OcrProviderException('PaddleOCR is not reachable. Start it with docker compose up -d paddleocr.');
        } catch (Throwable) {
            throw new OcrProviderException('PaddleOCR could not read the CNIC image.');
        }

        if ($response->failed()) {
            throw new OcrProviderException('PaddleOCR could not read the CNIC image.');
        }

        [$text, $confidence] = self::textFromPayload($response->json());

        if ($text === '') {
            throw new OcrProviderException('PaddleOCR did not find any text on the CNIC image.');
        }

        return new OcrProviderResult(
            text: $text,
            confidence: $confidence,
            provider: $this->name(),
        );
    }

    /**
     * @return array{0: string, 1: ?float}
     */
    public static function textFromPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return ['', null];
        }

        $lines = [];

        foreach ($payload['rec_texts'] ?? [] as $line) {
            if (is_string($line) && trim($line) !== '') {
                $lines[] = trim($line);
            }
        }

        $scores = [];

        foreach ($payload['rec_scores'] ?? [] as $score) {
            if (is_numeric($score)) {
                $scores[] = (float) $score;
            }
        }

        return [
            implode("\n", $lines),
            $scores === [] ? null : round(array_sum($scores) / count($scores), 2),
        ];
    }

    private function mime(string $path): string
    {
        $mime = @mime_content_type($path) ?: 'image/jpeg';

        return str_contains($mime, 'png') ? 'image/png' : 'image/jpeg';
    }
}
