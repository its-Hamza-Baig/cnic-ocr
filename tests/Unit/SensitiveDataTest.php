<?php

namespace Tests\Unit;

use App\Support\SensitiveData;
use Tests\TestCase;

class SensitiveDataTest extends TestCase
{
    public function test_it_masks_cnic_numbers(): void
    {
        $this->assertSame('35202-*****67-1', SensitiveData::maskCnic('35202-1234567-1'));
    }

    public function test_it_redacts_cnic_patterns_in_log_messages(): void
    {
        $this->assertSame('Saved *****', SensitiveData::redact('Saved 35202-1234567-1'));
        $this->assertSame('Saved *****', SensitiveData::redact('Saved 3520212345671'));
    }
}
