<?php

namespace Tests\Feature;

use App\Models\Person;

class UnauthorizedAccessTest extends FeatureTestCase
{
    public function test_guests_cannot_access_person_endpoints(): void
    {
        $person = Person::factory()->create();

        $this->get(route('persons.show', $person))->assertRedirect(route('login'));
        $this->get(route('persons.edit', $person))->assertRedirect(route('login'));
        $this->put(route('persons.update', $person), [])->assertRedirect(route('login'));
        $this->delete(route('persons.destroy', $person))->assertRedirect(route('login'));
        $this->post(route('persons.ocr'), [])->assertRedirect(route('login'));
        $this->post(route('persons.check-duplicate'), ['cnic' => '35202-1234567-1'])->assertRedirect(route('login'));
        $this->get(route('persons.image', [$person, 'front']))->assertRedirect(route('login'));
    }
}
