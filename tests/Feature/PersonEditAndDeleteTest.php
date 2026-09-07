<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\Person;

class PersonEditAndDeleteTest extends FeatureTestCase
{
    public function test_edit_flow_updates_fields(): void
    {
        $person = Person::factory()->create([
            'created_by' => $this->user->id,
            'cnic' => '35202-1234567-1',
            'name' => 'Old Name',
        ]);

        $this->actingAs($this->user)
            ->put(route('persons.update', $person), [
                'cnic' => '37405-1111111-2',
                'name' => 'New Name',
                'father_husband_name' => 'New Father',
                'address' => 'New Address',
                'verification_status' => VerificationStatus::Verified->value,
            ])
            ->assertRedirect(route('persons.show', $person));

        $person->refresh();
        $this->assertSame('37405-1111111-2', $person->cnic);
        $this->assertSame('New Name', $person->name);
        $this->assertSame(VerificationStatus::Verified, $person->verification_status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $person->id,
            'action' => 'updated',
        ]);
    }

    public function test_soft_delete_hides_the_record(): void
    {
        $person = Person::factory()->create([
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->delete(route('persons.destroy', $person))
            ->assertRedirect(route('persons.index'));

        $this->assertSoftDeleted($person);

        $this->actingAs($this->user)
            ->get(route('persons.index'))
            ->assertDontSee($person->cnic);
    }
}
