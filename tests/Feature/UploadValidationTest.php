<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;

class UploadValidationTest extends FeatureTestCase
{
    public function test_non_image_uploads_are_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('persons.ocr'), [
                'front_image' => UploadedFile::fake()->create('cnic.jpg', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('front_image');
    }

    public function test_oversized_images_are_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('persons.ocr'), [
                'front_image' => UploadedFile::fake()->image('cnic.jpg')->size(6000),
            ])
            ->assertSessionHasErrors('front_image');
    }

    public function test_valid_jpeg_images_are_accepted(): void
    {
        $this->actingAs($this->user)
            ->post(route('persons.ocr'), [
                'front_image' => $this->fakeCnicImage(),
            ])
            ->assertRedirect(route('persons.create'));
    }
}
