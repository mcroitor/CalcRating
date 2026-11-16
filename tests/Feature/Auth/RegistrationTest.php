<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_with_name_and_surname(): void
    {
        $response = $this->post('/register', [
            'name' => 'Иван',
            'surname' => 'Иванов',
            'email' => 'ivan@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'ivan@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Иван', $user->name);
        $this->assertSame('Иванов', $user->surname);
        $this->assertSame(UserRole::Guest, $user->role);
        $this->assertSame(1, $user->version);
    }

    public function test_registration_fails_without_surname(): void
    {
        $response = $this->post('/register', [
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('surname');
        $this->assertGuest();
    }
}
