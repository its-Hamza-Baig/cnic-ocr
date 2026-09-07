<?php

namespace Tests\Feature;

use App\Enums\OcrScanStatus;
use App\Enums\VerificationStatus;
use App\Models\OcrScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('cnic');
        $this->user = User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function pendingScan(array $overrides = []): array
    {
        Storage::disk('cnic')->put('tmp/front-test.jpg', 'image-bytes');

        $scan = OcrScan::query()->create([
            'provider' => 'fake',
            'status' => OcrScanStatus::Completed,
            'confidence' => 0.91,
            'processing_time_ms' => 12,
        ]);

        return array_merge([
            'ocr_scan_id' => $scan->id,
            'front_image_path' => 'tmp/front-test.jpg',
            'back_image_path' => null,
            'cnic' => '35202-1234567-1',
            'name' => 'ALI RAZA',
            'father_husband_name' => 'MUHAMMAD RAZA',
            'address' => 'HOUSE 12, STREET 4, GULBERG, LAHORE',
            'confidence' => 0.91,
            'provider' => 'fake',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function validPersonPayload(array $overrides = []): array
    {
        return array_merge([
            'cnic' => '35202-1234567-1',
            'name' => 'ALI RAZA',
            'father_husband_name' => 'MUHAMMAD RAZA',
            'address' => 'HOUSE 12, STREET 4, GULBERG, LAHORE',
            'verification_status' => VerificationStatus::Pending->value,
            'confirmed' => '1',
        ], $overrides);
    }

    protected function fakeCnicImage(string $name = 'cnic.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 640, 400);
    }
}
