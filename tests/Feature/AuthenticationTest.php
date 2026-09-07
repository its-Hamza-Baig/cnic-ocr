<?php

namespace Tests\Feature;

use App\Models\User;

class AuthenticationTest extends FeatureTestCase
{
    public function test_guests_are_redirected_from_management_screens(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/persons')->assertRedirect(route('login'));
        $this->get('/persons/create')->assertRedirect(route('login'));
    }

    public function test_users_can_log_in_and_out(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@example.com',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'operator@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_rejected_for_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'operator@example.com',
            'password' => 'password',
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'operator@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
