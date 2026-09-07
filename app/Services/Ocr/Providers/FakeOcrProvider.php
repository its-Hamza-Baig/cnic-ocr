<?php

namespace App\Services\Ocr\Providers;

use App\Contracts\OcrProviderInterface;
use App\DTOs\OcrProviderResult;
use App\Exceptions\OcrProviderException;

class FakeOcrProvider implements OcrProviderInterface
{
    public static ?string $text = null;

    public static ?string $backText = null;

    public static bool $fail = false;

    public static ?float $confidence = 0.91;

    public function name(): string
    {
        return 'fake';
    }

    public function recognize(string $absoluteImagePath, string $side = 'front'): OcrProviderResult
    {
        if (self::$fail) {
            throw new OcrProviderException('OCR provider unavailable.');
        }

        $fixture = $side === 'back'
            ? base_path('tests/Fixtures/ocr/sample-cnic-back.txt')
            : base_path('tests/Fixtures/ocr/sample-cnic.txt');
        $text = $side === 'back'
            ? (self::$backText ?? (is_file($fixture) ? (string) file_get_contents($fixture) : ''))
            : (self::$text ?? (is_file($fixture) ? (string) file_get_contents($fixture) : ''));

        return new OcrProviderResult(
            text: $text,
            confidence: self::$confidence,
            provider: $this->name(),
        );
    }

    public static function reset(): void
    {
        self::$text = null;
        self::$backText = null;
        self::$fail = false;
        self::$confidence = 0.91;
    }
}
