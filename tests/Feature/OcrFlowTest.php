<?php

namespace Tests\Feature;

use App\Enums\OcrScanStatus;
use App\Models\Person;
use App\Services\Ocr\Providers\FakeOcrProvider;

class OcrFlowTest extends FeatureTestCase
{
    public function test_ocr_result_appears_in_editable_fields(): void
    {
        $this->actingAs($this->user)
            ->post(route('persons.ocr'), [
                'front_image' => $this->fakeCnicImage(),
            ])
            ->assertRedirect(route('persons.create'));

        $this->actingAs($this->user)
            ->get(route('persons.create'))
            ->assertOk()
            ->assertSee('machine-extracted')
            ->assertSee('ALI RAZA')
            ->assertSee('MUHAMMAD RAZA')
            ->assertSee('35202-1234567-1')
            ->assertSee('HOUSE 12, STREET 4, GULBERG, LAHORE');

        $this->assertDatabaseHas('ocr_scans', [
            'status' => OcrScanStatus::Completed->value,
            'person_id' => null,
        ]);
        $this->assertDatabaseCount('persons', 0);
    }

    public function test_malformed_ocr_still_shows_review_form(): void
    {
        FakeOcrProvider::$text = (string) file_get_contents(base_path('tests/Fixtures/ocr/malformed.txt'));

        $this->actingAs($this->user)
            ->post(route('persons.ocr'), [
                'front_image' => $this->fakeCnicImage(),
            ])
            ->assertRedirect(route('persons.create'));

        $this->actingAs($this->user)
            ->get(route('persons.create'))
            ->assertOk()
            ->assertSee('machine-extracted');
    }

    public function test_ocr_provider_failure_does_not_save_a_person(): void
    {
        FakeOcrProvider::$fail = true;

        $this->actingAs($this->user)
            ->from(route('persons.create'))
            ->post(route('persons.ocr'), [
                'front_image' => $this->fakeCnicImage(),
            ])
            ->assertRedirect(route('persons.create'))
            ->assertSessionHasErrors('front_image');

        $this->assertDatabaseCount('persons', 0);
        $this->assertDatabaseHas('ocr_scans', [
            'status' => OcrScanStatus::Failed->value,
        ]);
    }

    public function test_nothing_is_saved_before_confirmation(): void
    {
        $this->actingAs($this->user)
            ->withSession(['pending_cnic_scan' => $this->pendingScan()])
            ->post(route('persons.store'), $this->validPersonPayload(['confirmed' => '']))
            ->assertSessionHasErrors('confirmed');

        $this->assertDatabaseCount('persons', 0);
    }

    public function test_successful_save_after_confirmation(): void
    {
        $this->actingAs($this->user)
            ->withSession(['pending_cnic_scan' => $this->pendingScan()])
            ->post(route('persons.store'), $this->validPersonPayload([
                'cnic' => '3520212345671',
                'name' => 'Corrected Name',
            ]))
            ->assertRedirect();

        $person = Person::query()->first();
        $this->assertNotNull($person);
        $this->assertSame('35202-1234567-1', $person->cnic);
        $this->assertSame('Corrected Name', $person->name);
        $this->assertNotNull($person->front_image_path);
        $this->assertDatabaseHas('ocr_scans', [
            'person_id' => $person->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $person->id,
            'action' => 'created',
        ]);
    }
}
