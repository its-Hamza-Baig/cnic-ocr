<?php

namespace App\Contracts;

use App\DTOs\OcrScanOutcome;

interface OcrServiceInterface
{
    public function process(string $frontAbsolutePath, ?string $backAbsolutePath = null): OcrScanOutcome;
}
