<?php

namespace App\DTOs;

use App\Models\OcrScan;

class OcrScanOutcome
{
    public function __construct(
        public readonly OcrScan $scan,
        public readonly ParsedCnic $parsed,
        public readonly ?float $confidence,
        public readonly string $provider,
        public readonly int $processingTimeMs,
    ) {}
}
