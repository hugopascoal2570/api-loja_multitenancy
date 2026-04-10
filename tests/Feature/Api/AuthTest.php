<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name'                  => 'João',
            'last_name'             => 'Silva',
            'email'                 => 'joao@teste.com',
            'password'              => 'senha123',
            'password_confirmation' => 'senha123',
            'device_name'           => 'test',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseHas('users', ['email' => 'joao@teste.com']);
    }

    public function test_user_cannot_register_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'joao@teste.com']);

        $response = $this->postJson('/api/register', [
            'name'                  => 'João',
            'email'                 => 'joao@teste.com',
            'password'              => 'senha123',
            'password_confirmation' => 'senha123',
            'device_name'           => 'test',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email'    => 'joao@teste.com',
            'password' => bcrypt('senha123'),
        ]);

        $response = $this->postJson('/api/auth', [
            'email'       => 'joao@teste.com',
            'password'    => 'senha123',
            'device_name' => 'test',
        ]);

        $response->assertOk()
                 ->assertJsonStructure(['token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'joao@teste.com',
            'password' => bcrypt('senha123'),
        ]);

        $response = $this->postJson('/api/auth', [
            'email'       => 'joao@teste.com',
            'password'    => 'errada',
            'device_name' => 'test',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_get_own_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/me');

        $response->assertOk()
                 ->assertJsonPath('data.id', $user->id);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/logout');

        $response->assertNoContent();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }
}
