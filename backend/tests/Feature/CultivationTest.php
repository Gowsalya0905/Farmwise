<?php

namespace Tests\Feature;

use App\Models\Plot;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CultivationTest extends TestCase
{
    use RefreshDatabase;

    private function records(User $user): array
    {
        $farm = $user->farms()->create(['name' => 'Farm']);
        $plot = $farm->plots()->create(['name' => 'Plot', 'area_unit' => 'acre']);
        $crop = $user->crops()->create(['name' => 'Rice']);
        $season = Season::create(['name' => 'Monsoon', 'plot_id' => $plot->id, 'crop_id' => $crop->id, 'start_date' => '2026-06-01', 'status' => 'active']);

        return compact('farm', 'plot', 'crop', 'season');
    }

    public function test_guests_are_rejected_on_every_action(): void
    {
        foreach (['plots', 'crops', 'seasons'] as $module) {
            $this->getJson('/api/'.$module)->assertUnauthorized();
            $this->getJson('/api/'.$module.'/1')->assertUnauthorized();
            $this->postJson('/api/'.$module, [])->assertUnauthorized();
            $this->putJson('/api/'.$module.'/1', [])->assertUnauthorized();
            $this->deleteJson('/api/'.$module.'/1')->assertUnauthorized();
        }
    }

    public function test_records_and_relationships_are_isolated(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $own = $this->records($alice);
        $foreign = $this->records($bob);
        $this->actingAs($alice)->withSession(['auth_password_hash' => $alice->password]);
        foreach (['plots' => 'plot', 'crops' => 'crop', 'seasons' => 'season'] as $module => $key) {
            $this->getJson('/api/'.$module)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own[$key]->id);
            $this->getJson('/api/'.$module.'/'.$foreign[$key]->id)->assertNotFound();
            $this->putJson('/api/'.$module.'/'.$foreign[$key]->id, [])->assertNotFound();
            $this->deleteJson('/api/'.$module.'/'.$foreign[$key]->id)->assertNotFound();
            $this->assertFalse(Gate::forUser($alice)->allows('update', $foreign[$key]));
        }
        $plot = ['name' => 'New', 'farm_id' => $foreign['farm']->id, 'area_unit' => 'acre'];
        $this->postJson('/api/plots', $plot)->assertUnprocessable();
        $this->putJson('/api/plots/'.$own['plot']->id, $plot)->assertUnprocessable();
        $season = ['name' => 'New', 'plot_id' => $own['plot']->id, 'crop_id' => $foreign['crop']->id, 'start_date' => '2026-01-01', 'status' => 'planned'];
        $this->postJson('/api/seasons', $season)->assertUnprocessable();
        $this->putJson('/api/seasons/'.$own['season']->id, $season)->assertUnprocessable();
        $season['crop_id'] = $own['crop']->id;
        $season['plot_id'] = $foreign['plot']->id;
        $this->postJson('/api/seasons', $season)->assertUnprocessable();
        $this->postJson('/api/crops', ['name' => 'Spoof', 'user_id' => $bob->id])->assertUnprocessable();
    }

    public function test_crud_validation_and_deletion_rules(): void
    {
        $user = User::factory()->create();
        $own = $this->records($user);
        $this->actingAs($user)->withSession(['auth_password_hash' => $user->password]);
        $this->postJson('/api/crops', ['name' => 'Wheat', 'variety' => 'Local'])->assertCreated()->assertJsonPath('user_id', $user->id);
        $plot = ['name' => 'North', 'farm_id' => $own['farm']->id, 'area' => '1.2500', 'area_unit' => 'hectare'];
        $id = $this->postJson('/api/plots', $plot)->assertCreated()->json('id');
        $this->putJson('/api/plots/'.$id, array_replace($plot, ['name' => 'South']))->assertOk()->assertJsonPath('name', 'South');
        $this->postJson('/api/plots', array_replace($plot, ['area' => -1]))->assertUnprocessable();
        $this->postJson('/api/plots', array_replace($plot, ['area_unit' => 'unknown']))->assertUnprocessable();
        $season = ['name' => 'Winter', 'plot_id' => $id, 'crop_id' => $own['crop']->id, 'start_date' => '2026-10-01', 'end_date' => '2027-01-01', 'status' => 'planned'];
        $seasonId = $this->postJson('/api/seasons', $season)->assertCreated()->json('id');
        $this->putJson('/api/seasons/'.$seasonId, array_replace($season, ['status' => 'completed']))->assertOk();
        $this->postJson('/api/seasons', array_replace($season, ['end_date' => '2026-01-01']))->assertUnprocessable();
        $this->postJson('/api/seasons', array_replace($season, ['status' => 'unknown']))->assertUnprocessable();
        $this->deleteJson('/api/farms/'.$own['farm']->id)->assertStatus(409);
        $this->deleteJson('/api/plots/'.$id)->assertStatus(409);
        $this->deleteJson('/api/crops/'.$own['crop']->id)->assertStatus(409);
        $otherFarm = $user->farms()->create(['name' => 'Other']);
        $this->putJson('/api/plots/'.$id, array_replace($plot, ['farm_id' => $otherFarm->id]))->assertStatus(409);
        $this->deleteJson('/api/seasons/'.$seasonId)->assertNoContent();
        $this->deleteJson('/api/plots/'.$id)->assertNoContent();
        $this->assertNull(Plot::find($id));
        $this->putJson('/api/crops/'.$own['crop']->id, ['name' => 'Updated'])->assertOk();
    }

    public function test_mutations_require_csrf(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth_password_hash' => $user->password]);
        $this->app['env'] = 'local';
        foreach (['plots', 'crops', 'seasons'] as $module) {
            $this->postJson('/api/'.$module, [])->assertStatus(419);
        }
    }
}
