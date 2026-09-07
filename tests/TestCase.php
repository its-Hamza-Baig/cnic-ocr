<?php

namespace Tests;

use App\Services\Ocr\Providers\FakeOcrProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        FakeOcrProvider::reset();
    }
}
