<?php

namespace Tests\Feature;

use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_and_monthly_finances_are_owner_scoped(): void
    {
        $this->travelTo(Carbon::parse('2026-10-06 12:00:00', 'Asia/Kolkata'));
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $users = [User::factory()->create(), User::factory()->create()];
        foreach ($users as $user) {
            $farm = $user->farms()->create(['name' => 'Farm']);
            $plot = $farm->plots()->create(['name' => 'Plot', 'area_unit' => 'acre']);
            $crop = $user->crops()->create(['name' => 'Rice']);
            $season = Season::create(['name' => 'Season', 'plot_id' => $plot->id, 'crop_id' => $crop->id, 'start_date' => '2026-01-01', 'status' => 'active']);
            $season->activities()->create(['type' => 'Watering', 'date' => '2026-10-01']);
            $season->activities()->create(['type' => 'Old', 'date' => '2026-09-30']);
            $season->expenses()->create(['category' => 'Seeds', 'date' => '2026-10-01', 'amount' => '10.10', 'currency' => 'INR']);
            $season->expenses()->create(['category' => 'Seeds', 'date' => '2026-10-01', 'amount' => '2.00', 'currency' => 'USD']);
            $season->expenses()->create(['category' => 'Old', 'date' => '2026-09-30', 'amount' => '100', 'currency' => 'INR']);
            $harvest = $season->harvests()->create(['date' => '2026-10-01', 'quantity' => '10', 'unit' => 'kg']);
            $harvest->sales()->create(['date' => '2026-10-02', 'quantity' => '1', 'unit' => 'kg', 'unit_price' => '20', 'total' => '20', 'currency' => 'INR']);
        }
        $user = $users[0];
        $this->actingAs($user)->withSession(['auth_password_hash' => $user->password]);
        $response = $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('counts.farms', 1)
            ->assertJsonPath('counts.plots', 1)->assertJsonPath('counts.crops', 1)->assertJsonPath('counts.active_seasons', 1)
            ->assertJsonPath('counts.planned_seasons', 0)->assertJsonPath('counts.completed_seasons', 0)
            ->assertJsonPath('counts.month_activities', 1)->assertJsonPath('counts.month_harvests', 1)
            ->assertJsonPath('month_label', 'October 2026');
        $financials = collect($response->json('financials'))->keyBy('currency');
        $this->assertSame('10.10', $financials['INR']['expenses']);
        $this->assertSame('20.00', $financials['INR']['sales']);
        $this->assertSame('9.90', $financials['INR']['net']);
        $this->assertSame('-2.00', $financials['USD']['net']);
    }
}
