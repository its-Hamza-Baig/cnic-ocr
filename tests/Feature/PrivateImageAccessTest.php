<?php

namespace Tests\Feature;

use App\Models\Person;
use Illuminate\Support\Facades\Storage;

class PrivateImageAccessTest extends FeatureTestCase
{
    public function test_private_images_cannot_be_accessed_anonymously(): void
    {
        $person = Person::factory()->create([
            'created_by' => $this->user->id,
            'front_image_path' => 'persons/1/front-test.jpg',
        ]);

        Storage::disk('cnic')->put('persons/1/front-test.jpg', 'secret-bytes');

        $this->get(route('persons.image', [$person, 'front']))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_private_images(): void
    {
        $person = Person::factory()->create([
            'created_by' => $this->user->id,
            'front_image_path' => 'persons/1/front-test.jpg',
        ]);

        Storage::disk('cnic')->put('persons/1/front-test.jpg', 'secret-bytes');

        $this->actingAs($this->user)
            ->get(route('persons.image', [$person, 'front']))
            ->assertOk();
    }
}
