<?php

namespace App\Services;

use App\Contracts\OcrProviderInterface;
use App\Contracts\OcrServiceInterface;
use App\DTOs\OcrProviderResult;
use App\DTOs\OcrScanOutcome;
use App\Enums\OcrScanStatus;
use App\Exceptions\OcrProviderException;
use App\Models\OcrScan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

class OcrService implements OcrServiceInterface
{
    public function __construct(
        private readonly OcrProviderInterface $provider,
        private readonly CnicParserService $parser,
    ) {}

    public function process(string $frontAbsolutePath, ?string $backAbsolutePath = null): OcrScanOutcome
    {
        $scan = OcrScan::query()->create([
            'provider' => $this->provider->name(),
            'status' => OcrScanStatus::Processing,
        ]);

        $started = (int) round(microtime(true) * 1000);

        try {
            $front = $this->provider->recognize($frontAbsolutePath, 'front');
            $back = $backAbsolutePath ? $this->provider->recognize($backAbsolutePath, 'back') : null;
            $parsed = $this->parser->parse($front->text, $back?->text);
            $elapsed = max(0, (int) round(microtime(true) * 1000) - $started);
            $confidence = $this->mergeConfidence($front, $back);

            $scan->fill([
                'status' => OcrScanStatus::Completed,
                'confidence' => $confidence,
                'processing_time_ms' => $elapsed,
                'provider' => $front->provider,
            ]);

            if (config('ocr.store_raw_text')) {
                $combined = trim($front->text."\n".($back?->text ?? ''));
                $scan->raw_text_encrypted = Crypt::encryptString($combined);
                $scan->raw_expires_at = now()->addHours((int) config('ocr.raw_text_retention_hours', 24));
            }

            $scan->save();

            return new OcrScanOutcome(
                scan: $scan,
                parsed: $parsed,
                confidence: $confidence,
                provider: $front->provider,
                processingTimeMs: $elapsed,
            );
        } catch (OcrProviderException $exception) {
            $this->markFailed($scan, $started);
            throw $exception;
        } catch (Throwable $exception) {
            $this->markFailed($scan, $started);
            Log::warning('ocr.provider_failed', ['scan_id' => $scan->id]);
            throw new OcrProviderException;
        }
    }

    private function markFailed(OcrScan $scan, int $started): void
    {
        $scan->update([
            'status' => OcrScanStatus::Failed,
            'processing_time_ms' => max(0, (int) round(microtime(true) * 1000) - $started),
        ]);
    }

    private function mergeConfidence(OcrProviderResult $front, ?OcrProviderResult $back): ?float
    {
        $values = array_values(array_filter(
            [$front->confidence, $back?->confidence],
            fn ($value) => $value !== null
        ));

        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }
}
