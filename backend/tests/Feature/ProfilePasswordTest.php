<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ProfilePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function farmer(): User
    {
        return User::factory()->create(['password' => Hash::make('original-password')]);
    }

    private function signIn(User $user): void
    {
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'original-password'])->assertOk();
    }

    private function resetData(User $user, string $token): array
    {
        return ['email' => $user->email, 'token' => $token, 'password' => 'replacement-password', 'password_confirmation' => 'replacement-password'];
    }

    public function test_profile_requires_authentication_and_rejects_account_selection(): void
    {
        $this->patchJson('/api/auth/profile', ['name' => 'Guest', 'email' => 'guest@example.com'])->assertUnauthorized();
        $this->putJson('/api/auth/password', [])->assertUnauthorized();
        $alice = $this->farmer();
        $bob = $this->farmer();
        $this->signIn($alice);
        foreach (['id', 'user_id'] as $field) {
            $this->patchJson('/api/auth/profile', ['name' => 'Stolen', 'email' => $alice->email, $field => $bob->id])->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->putJson('/api/auth/password', ['current_password' => 'original-password', 'password' => 'replacement-password', 'password_confirmation' => 'replacement-password', $field => $bob->id])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame($bob->name, $bob->fresh()->name);
        $this->assertTrue(Hash::check('original-password', $bob->fresh()->password));
    }

    public function test_profile_name_changes_without_password_and_validates_input(): void
    {
        $user = $this->farmer();
        $this->signIn($user);
        $this->patchJson('/api/auth/profile', ['name' => 'Updated Farmer', 'email' => strtoupper($user->email)])
            ->assertOk()->assertJsonPath('user.name', 'Updated Farmer')->assertJsonPath('user.email', $user->email)->assertJsonMissingPath('user.password');
        $this->patchJson('/api/auth/profile', ['name' => '', 'email' => 'not-email'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email']);
        $this->getJson('/api/auth/user')->assertOk()->assertJsonPath('user.name', 'Updated Farmer');
    }

    public function test_email_changes_require_correct_password_and_unique_email(): void
    {
        $user = $this->farmer();
        $other = $this->farmer();
        $token = Password::createToken($user);
        $this->signIn($user);
        foreach ([null, 'wrong-password'] as $password) {
            $this->patchJson('/api/auth/profile', ['name' => $user->name, 'email' => 'new@example.com', 'current_password' => $password])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        }
        $this->patchJson('/api/auth/profile', ['name' => $user->name, 'email' => strtoupper($other->email), 'current_password' => 'original-password'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->patchJson('/api/auth/profile', ['name' => $user->name, 'email' => ' NEW@example.com ', 'current_password' => 'original-password'])->assertOk()->assertJsonPath('user.email', 'new@example.com');
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertFalse(Password::tokenExists($user, $token));
        $this->assertSame($other->email, $other->fresh()->email);
    }

    public function test_password_change_validates_current_password_and_confirmation(): void
    {
        $user = $this->farmer();
        $this->signIn($user);
        $data = ['current_password' => 'wrong', 'password' => 'replacement-password', 'password_confirmation' => 'replacement-password'];
        $this->putJson('/api/auth/password', $data)->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $data['current_password'] = 'original-password';
        $data['password_confirmation'] = 'different-password';
        $this->putJson('/api/auth/password', $data)->assertUnprocessable()->assertJsonValidationErrors('password');
        $data['password'] = $data['password_confirmation'] = 'short';
        $this->putJson('/api/auth/password', $data)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check('original-password', $user->fresh()->password));
    }

    public function test_password_change_keeps_current_session_and_rejects_old_sessions(): void
    {
        $user = $this->farmer();
        $oldHash = $user->password;
        $token = Password::createToken($user);
        $this->signIn($user);
        $this->putJson('/api/auth/password', ['current_password' => 'original-password', 'password' => 'replacement-password', 'password_confirmation' => 'replacement-password'])->assertOk();
        $this->assertTrue(Hash::check('replacement-password', $user->fresh()->password));
        $this->assertFalse(Password::tokenExists($user, $token));
        $this->getJson('/api/auth/user')->assertOk();
        $this->getJson('/api/farms')->assertOk();
        $this->actingAs($user->fresh())->withSession(['auth_password_hash' => $oldHash]);
        $this->getJson('/api/auth/user')->assertUnauthorized();
        $this->getJson('/api/farms')->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'original-password'])->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'replacement-password'])->assertOk();
    }

    public function test_reset_requests_are_identical_for_known_unknown_and_broker_throttled_emails(): void
    {
        Notification::fake();
        $user = $this->farmer();
        $known = $this->postJson('/api/auth/forgot-password', ['email' => strtoupper($user->email)])->assertOk()->assertJsonMissingPath('token')->json();
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'absent@example.com'])->assertOk()->json();
        $throttled = $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk()->json();
        $this->assertSame($known, $unknown);
        $this->assertSame($known, $throttled);
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;
            $this->assertStringStartsWith(config('app.frontend_url').'/reset-password?', $url);
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $this->assertSame($user->email, $query['email']);
            $this->assertSame($notification->token, $query['token']);
            $this->assertTrue(Password::tokenExists($user, $notification->token));
            $this->assertNotSame($notification->token, DB::table('password_reset_tokens')->where('email', $user->email)->value('token'));

            return true;
        });
    }

    public function test_delivery_errors_do_not_reveal_account_existence(): void
    {
        $user = $this->farmer();
        Password::shouldReceive('sendResetLink')->twice()->andThrow(new \RuntimeException('SMTP transport unavailable'));
        $known = $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk()->json();
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'absent@example.com'])->assertOk()->json();
        $this->assertSame($known, $unknown);
        $this->assertArrayNotHasKey('token', $known);
    }

    public function test_reset_request_and_reset_attempt_rate_limits(): void
    {
        Notification::fake();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/forgot-password', ['email' => 'absent@example.com'])->assertOk();
        }
        $this->postJson('/api/auth/forgot-password', ['email' => 'absent@example.com'])->assertStatus(429);
        // Use another IP because Laravel's unnamed route limiter shares the IP bucket.
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20']);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/reset-password', [])->assertUnprocessable();
        }
        $this->postJson('/api/auth/reset-password', [])->assertStatus(429);
    }

    public function test_invalid_expired_wrong_email_and_reused_reset_tokens_are_rejected(): void
    {
        $user = $this->farmer();
        $other = $this->farmer();
        $token = Password::createToken($user);
        $this->postJson('/api/auth/reset-password', $this->resetData($user, 'invalid-token'))->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->postJson('/api/auth/reset-password', $this->resetData($other, $token))->assertUnprocessable();
        $this->travel(61)->minutes();
        $this->postJson('/api/auth/reset-password', $this->resetData($user, $token))->assertUnprocessable();
        $this->assertTrue(Hash::check('original-password', $user->fresh()->password));
        $freshToken = Password::createToken($user);
        $this->postJson('/api/auth/reset-password', $this->resetData($user, $freshToken))->assertOk()->assertJsonMissingPath('token');
        $this->postJson('/api/auth/reset-password', $this->resetData($user, $freshToken))->assertUnprocessable();
    }

    public function test_reset_validates_confirmation_and_never_accepts_account_ids(): void
    {
        $user = $this->farmer();
        $token = Password::createToken($user);
        $data = $this->resetData($user, $token);
        $data['password_confirmation'] = 'different-password';
        $this->postJson('/api/auth/reset-password', $data)->assertUnprocessable()->assertJsonValidationErrors('password');
        $data = $this->resetData($user, $token);
        $data['user_id'] = $user->id;
        $this->postJson('/api/auth/reset-password', $data)->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->assertTrue(Password::tokenExists($user, $token));
    }

    public function test_reset_invalidates_existing_sessions_and_rotates_remember_token(): void
    {
        $user = $this->farmer();
        $oldHash = $user->password;
        $oldRemember = $user->remember_token;
        $token = Password::createToken($user);
        $this->signIn($user);
        $this->postJson('/api/auth/reset-password', $this->resetData($user, $token))->assertOk();
        $this->getJson('/api/auth/user')->assertUnauthorized();
        $this->assertNotSame($oldRemember, $user->fresh()->remember_token);
        // Simulate another browser restoring its pre-reset session, not just the reset browser.
        $this->actingAs($user->fresh())->withSession(['auth_password_hash' => $oldHash]);
        $this->getJson('/api/farms')->assertUnauthorized();
        $this->postJson('/api/farms', ['name' => 'Unauthorized'])->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'replacement-password'])->assertOk();
        $this->getJson('/api/auth/user')->assertOk();
    }

    public function test_sessions_without_login_hash_are_rejected(): void
    {
        $this->actingAs($this->farmer());
        $this->getJson('/api/auth/user')->assertUnauthorized();
    }

    public function test_profile_and_recovery_endpoints_require_csrf(): void
    {
        $user = $this->farmer();
        $this->actingAs($user)->withSession(['auth_password_hash' => $user->password]);
        $this->app['env'] = 'local';
        $this->postJson('/api/auth/forgot-password', ['email' => 'absent@example.com'])->assertStatus(419);
        $this->postJson('/api/auth/reset-password', [])->assertStatus(419);
        $this->patchJson('/api/auth/profile', [])->assertStatus(419);
        $this->putJson('/api/auth/password', [])->assertStatus(419);
    }
}
