<?php

namespace App\Providers;

use App\Contracts\OcrProviderInterface;
use App\Contracts\OcrServiceInterface;
use App\Services\Ocr\Providers\FakeOcrProvider;
use App\Services\Ocr\Providers\GoogleVisionOcrProvider;
use App\Services\Ocr\Providers\PaddleOcrProvider;
use App\Services\Ocr\Providers\TesseractOcrProvider;
use App\Services\OcrService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OcrServiceInterface::class, OcrService::class);

        $this->app->bind(OcrProviderInterface::class, function () {
            return match (config('ocr.provider')) {
                'fake' => new FakeOcrProvider,
                'paddleocr' => new PaddleOcrProvider,
                'tesseract' => new TesseractOcrProvider,
                'google_vision' => new GoogleVisionOcrProvider,
                default => throw new InvalidArgumentException('Unsupported OCR provider.'),
            };
        });
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('ocr', function (Request $request) {
            return Limit::perMinute((int) config('ocr.rate_limit_per_minute', 10))
                ->by('ocr|'.($request->user()?->getAuthIdentifier() ?: $request->ip()));
        });

        RateLimiter::for('cnic-sensitive', function (Request $request) {
            return Limit::perMinute((int) config('cnic.sensitive_rate_limit_per_minute', 30))
                ->by('cnic|'.($request->user()?->getAuthIdentifier() ?: $request->ip()));
        });
    }
}
