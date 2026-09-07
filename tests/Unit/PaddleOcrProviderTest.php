<?php

namespace Tests\Unit;

use App\Services\Ocr\Providers\PaddleOcrProvider;
use Tests\TestCase;

class PaddleOcrProviderTest extends TestCase
{
    public function test_it_joins_recognized_lines_and_averages_confidence(): void
    {
        [$text, $confidence] = PaddleOcrProvider::textFromPayload([
            'rec_texts' => ['Name', 'Ali Khan', '  '],
            'rec_scores' => [0.9, 1.0],
        ]);

        $this->assertSame("Name\nAli Khan", $text);
        $this->assertSame(0.95, $confidence);
    }

    public function test_it_handles_empty_paddleocr_payloads(): void
    {
        [$text, $confidence] = PaddleOcrProvider::textFromPayload(['rec_texts' => []]);

        $this->assertSame('', $text);
        $this->assertNull($confidence);
    }
}
