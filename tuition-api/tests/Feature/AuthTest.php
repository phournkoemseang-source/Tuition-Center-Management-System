<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Feature\ApiTestCase;

class AuthTest extends ApiTestCase
{
    public function test_login_returns_token_and_user(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@test.example',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']])
            ->assertJsonPath('user.role', 'admin');
    }

    public function test_login_rejects_wrong_password(): void
    {
        $this->postJson('/api/login', [
            'email' => 'admin@test.example',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_rejects_unknown_email(): void
    {
        $this->postJson('/api/login', [
            'email' => 'nobody@test.example',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_me_returns_current_user(): void
    {
        $this->actingAsAdmin()
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@test.example');
    }

    public function test_logout_revokes_token(): void
    {
        $token = $this->postJson('/api/login', [
            'email' => 'admin@test.example',
            'password' => 'password',
        ])->json('token');

        $this->postJson('/api/logout', [], ['Authorization' => "Bearer {$token}"])->assertOk();

        // In-process requests share the auth guard instance (which memoizes the user);
        // reset it so the next request re-resolves the (now revoked) token.
        app('auth')->forgetGuards();

        $this->getJson('/api/me', ['Authorization' => "Bearer {$token}"])
            ->assertUnauthorized();
    }

    public function test_teacher_role_is_returned(): void
    {
        $this->postJson('/api/login', [
            'email' => 'teacher@test.example',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.role', 'teacher');
    }
}
