<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_login_and_logout(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Farmer', 'email' => 'FARMER@example.com',
            'password' => 'strong-password', 'password_confirmation' => 'strong-password',
        ])->assertCreated()->assertJsonPath('user.email', 'farmer@example.com')->assertJsonMissingPath('user.password');
        $this->assertTrue(Hash::check('strong-password', User::first()->password));
        $this->getJson('/api/auth/user')->assertOk();
        $this->postJson('/api/auth/logout')->assertNoContent();
        $this->getJson('/api/auth/user')->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => 'farmer@example.com', 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => 'farmer@example.com', 'password' => 'strong-password'])->assertOk();
    }

    public function test_guest_cannot_access_any_farm_action(): void
    {
        $this->getJson('/api/farms')->assertUnauthorized();
        $this->postJson('/api/farms', ['name' => 'Farm'])->assertUnauthorized();
        $this->getJson('/api/farms/1')->assertUnauthorized();
        $this->patchJson('/api/farms/1', ['name' => 'Farm'])->assertUnauthorized();
        $this->deleteJson('/api/farms/1')->assertUnauthorized();
    }

    public function test_two_farmers_cannot_read_modify_delete_or_assign_each_others_farms(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $own = $alice->farms()->create(['name' => 'Alice farm']);
        $foreign = $bob->farms()->create(['name' => 'Bob farm']);
        $this->actingAs($alice);
        $this->getJson('/api/farms')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson('/api/farms/'.$foreign->id)->assertNotFound();
        $this->patchJson('/api/farms/'.$foreign->id, ['name' => 'Stolen'])->assertNotFound();
        $this->deleteJson('/api/farms/'.$foreign->id)->assertNotFound();
        $this->postJson('/api/farms', ['name' => 'Spoof', 'user_id' => $bob->id])->assertUnprocessable();
        $this->patchJson('/api/farms/'.$own->id, ['name' => 'Spoof', 'user_id' => $bob->id])->assertUnprocessable();
        $this->postJson('/api/farms', ['name' => 'New farm'])->assertCreated()->assertJsonPath('user_id', $alice->id);
        $this->patchJson('/api/farms/'.$own->id, ['name' => 'Updated'])->assertOk();
        $this->deleteJson('/api/farms/'.$own->id)->assertNoContent();
        $this->assertDatabaseHas('farms', ['id' => $foreign->id, 'name' => 'Bob farm', 'user_id' => $bob->id]);
        $this->assertFalse(Gate::forUser($alice)->allows('view', $foreign));
        $this->assertFalse(Gate::forUser($alice)->allows('update', $foreign));
        $this->assertFalse(Gate::forUser($alice)->allows('delete', $foreign));
    }

    public function test_csrf_is_required_even_for_login(): void
    {
        $this->app['env'] = 'local';
        $this->postJson('/api/auth/login', ['email' => 'farmer@example.com', 'password' => 'password'])->assertStatus(419);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertStatus(429);
    }
}
