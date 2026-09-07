<?php

namespace Tests\Unit;

use App\Services\CnicNormalizerService;
use Tests\TestCase;

class CnicNormalizerServiceTest extends TestCase
{
    private CnicNormalizerService $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new CnicNormalizerService;
    }

    public function test_it_normalizes_digits_only_input(): void
    {
        $this->assertSame('12345-1234567-1', $this->normalizer->normalize('1234512345671'));
    }

    public function test_it_normalizes_spaced_and_already_formatted_values(): void
    {
        $this->assertSame('35202-1234567-1', $this->normalizer->normalize('35202 1234567 1'));
        $this->assertSame('35202-1234567-1', $this->normalizer->normalize('35202-1234567-1'));
    }

    public function test_it_validates_normalized_cnic(): void
    {
        $this->assertTrue($this->normalizer->isValid('1234512345671'));
        $this->assertTrue($this->normalizer->isValid('12345-1234567-1'));
        $this->assertFalse($this->normalizer->isValid('12345-1234567'));
        $this->assertFalse($this->normalizer->isValid('abcd'));
        $this->assertFalse($this->normalizer->isValid(null));
    }
}
