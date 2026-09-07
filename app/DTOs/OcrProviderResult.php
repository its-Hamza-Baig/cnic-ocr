<?php

namespace App\DTOs;

class OcrProviderResult
{
    public function __construct(
        public readonly string $text,
        public readonly ?float $confidence,
        public readonly string $provider,
    ) {}
}
