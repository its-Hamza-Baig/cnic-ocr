<?php

namespace App\Contracts;

use App\DTOs\OcrProviderResult;

interface OcrProviderInterface
{
    public function recognize(string $absoluteImagePath, string $side = 'front'): OcrProviderResult;

    public function name(): string;
}
