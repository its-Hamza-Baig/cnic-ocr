<?php

namespace Tests\Feature;

use App\Models\Person;
use Illuminate\Support\Facades\Schema;

class DuplicateCnicTest extends FeatureTestCase
{
    public function test_duplicate_precheck_returns_existing_record_link(): void
    {
        $existing = Person::factory()->create([
            'cnic' => '35202-1234567-1',
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('persons.check-duplicate'), [
                'cnic' => '3520212345671',
            ])
            ->assertOk()
            ->assertJson([
                'duplicate' => true,
                'person_id' => $existing->id,
            ]);
    }

    public function test_duplicate_cnic_is_rejected_on_save(): void
    {
        Person::factory()->create([
            'cnic' => '35202-1234567-1',
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->withSession(['pending_cnic_scan' => $this->pendingScan()])
            ->post(route('persons.store'), $this->validPersonPayload())
            ->assertSessionHasErrors('cnic')
            ->assertSessionHas('duplicate_person_id');

        $this->assertDatabaseCount('persons', 1);
    }

    public function test_database_unique_constraint_prevents_duplicate_cnics(): void
    {
        $indexes = Schema::getIndexes('persons');

        $uniqueCnic = collect($indexes)->first(function (array $index) {
            return ($index['unique'] ?? false) && in_array('cnic', $index['columns'], true);
        });

        $this->assertNotNull($uniqueCnic);
    }
}
