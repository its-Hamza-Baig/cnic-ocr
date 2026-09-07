<?php

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        $digits = fake()->unique()->numerify('#############');

        return [
            'cnic' => substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12, 1),
            'name' => fake()->name(),
            'father_husband_name' => fake()->name(),
            'address' => fake()->address(),
            'front_image_path' => null,
            'back_image_path' => null,
            'verification_status' => VerificationStatus::Pending,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => VerificationStatus::Verified,
        ]);
    }
}
