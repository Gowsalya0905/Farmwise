<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-06 12:00:00', 'Asia/Kolkata'));
    }

    private function setupFarmer(): array
    {
        $user = User::factory()->create();
        $farm = $user->farms()->create(['name' => 'Farm']);
        $plot = $farm->plots()->create(['name' => 'Plot', 'area_unit' => 'acre']);
        $crop = $user->crops()->create(['name' => 'Rice']);
        $season = Season::create(['name' => 'Monsoon', 'plot_id' => $plot->id, 'crop_id' => $crop->id, 'start_date' => '2026-01-01', 'status' => 'active']);
        $harvest = $season->harvests()->create(['date' => '2026-06-01', 'quantity' => '10.0000', 'unit' => 'kg']);

        return compact('user', 'farm', 'plot', 'crop', 'season', 'harvest');
    }

    private function payloads(array $records): array
    {
        return [
            'activities' => ['season_id' => $records['season']->id, 'date' => '2026-06-01', 'type' => 'Irrigation', 'notes' => 'Morning'],
            'expenses' => ['season_id' => $records['season']->id, 'date' => '2026-06-01', 'category' => 'Seeds', 'amount' => '10.10', 'currency' => 'INR'],
            'harvests' => ['season_id' => $records['season']->id, 'date' => '2026-06-01', 'quantity' => '5.5000', 'unit' => 'kg'],
            'sales' => ['harvest_id' => $records['harvest']->id, 'date' => '2026-06-02', 'quantity' => '1.2500', 'unit' => 'kg', 'unit_price' => '10.10', 'currency' => 'INR'],
        ];
    }

    private function signIn(array $records): void
    {
        $this->actingAs($records['user'])->withSession(['auth_password_hash' => $records['user']->password]);
    }

    public function test_guests_are_rejected_and_mutations_require_csrf(): void
    {
        foreach (['activities', 'expenses', 'harvests', 'sales'] as $module) {
            $this->getJson('/api/'.$module)->assertUnauthorized();
            $this->getJson('/api/'.$module.'/1')->assertUnauthorized();
            $this->postJson('/api/'.$module, [])->assertUnauthorized();
            $this->putJson('/api/'.$module.'/1', [])->assertUnauthorized();
            $this->deleteJson('/api/'.$module.'/1')->assertUnauthorized();
        }
        $this->getJson('/api/reports')->assertUnauthorized();
        $this->signIn($this->setupFarmer());
        $this->app['env'] = 'local';
        foreach (['activities', 'expenses', 'harvests', 'sales'] as $module) {
            $this->postJson('/api/'.$module, [])->assertStatus(419);
            $this->putJson('/api/'.$module.'/1', [])->assertStatus(419);
            $this->deleteJson('/api/'.$module.'/1')->assertStatus(419);
        }
    }

    public function test_crud_isolation_and_foreign_relationships(): void
    {
        $own = $this->setupFarmer();
        $foreign = $this->setupFarmer();
        $this->signIn($foreign);
        $foreignIds = [];
        foreach ($this->payloads($foreign) as $module => $payload) {
            $foreignIds[$module] = $this->postJson('/api/'.$module, $payload)->assertCreated()->json('id');
        }
        $this->signIn($own);
        foreach ($this->payloads($own) as $module => $payload) {
            $foreignId = $foreignIds[$module];
            $this->getJson('/api/'.$module.'/'.$foreignId)->assertNotFound();
            $this->putJson('/api/'.$module.'/'.$foreignId, $payload)->assertNotFound();
            $this->deleteJson('/api/'.$module.'/'.$foreignId)->assertNotFound();
            $id = $this->postJson('/api/'.$module, $payload)->assertCreated()->json('id');
            $listed = $this->getJson('/api/'.$module)->assertOk()->json('data');
            $this->assertNotContains($foreignId, array_column($listed, 'id'));
            $this->getJson('/api/'.$module.'/'.$id)->assertOk();
            $this->putJson('/api/'.$module.'/'.$id, $payload + ['notes' => 'Updated'])->assertOk();
            $parent = $module === 'sales' ? 'harvest_id' : 'season_id';
            $foreignParent = $foreign[$module === 'sales' ? 'harvest' : 'season']->id;
            $this->postJson('/api/'.$module, array_replace($payload, [$parent => $foreignParent]))->assertUnprocessable();
            $this->putJson('/api/'.$module.'/'.$id, array_replace($payload, [$parent => $foreignParent]))->assertUnprocessable();
            $this->postJson('/api/'.$module, $payload + ['user_id' => $foreign['user']->id])->assertUnprocessable();
            $this->deleteJson('/api/'.$module.'/'.$id)->assertNoContent();
        }
        $this->getJson('/api/reports?farm_id='.$foreign['farm']->id)->assertUnprocessable();
        $this->getJson('/api/reports?season_id='.$foreign['season']->id)->assertUnprocessable();
        $this->getJson('/api/reports')->assertOk()->assertJsonCount(0, 'financials');
        $sale = Sale::find($foreignIds['sales']);
        $this->assertFalse(Gate::forUser($own['user'])->allows('update', $sale));
    }

    public function test_sale_stock_units_dates_and_exact_total(): void
    {
        $own = $this->setupFarmer();
        $this->signIn($own);
        $sale = $this->payloads($own)['sales'];
        $id = $this->postJson('/api/sales', $sale)->assertCreated()->assertJsonPath('total', '12.63')->json('id');
        $this->postJson('/api/sales', array_replace($sale, ['quantity' => '9']))->assertUnprocessable();
        $this->postJson('/api/sales', array_replace($sale, ['unit' => 'tonne']))->assertUnprocessable();
        $this->postJson('/api/sales', array_replace($sale, ['date' => '2026-05-31']))->assertUnprocessable();
        $this->postJson('/api/sales', $sale + ['total' => '0.01'])->assertUnprocessable();
        $this->putJson('/api/sales/'.$id, array_replace($sale, ['quantity' => '10']))->assertOk()->assertJsonPath('total', '101.00');
        $this->postJson('/api/sales', $sale)->assertUnprocessable();
        $harvest = ['season_id' => $own['season']->id, 'date' => '2026-06-01', 'quantity' => '9', 'unit' => 'kg'];
        $this->putJson('/api/harvests/'.$own['harvest']->id, $harvest)->assertUnprocessable();
        $this->putJson('/api/harvests/'.$own['harvest']->id, array_replace($harvest, ['quantity' => '10', 'unit' => 'tonne']))->assertUnprocessable();
        $this->deleteJson('/api/harvests/'.$own['harvest']->id)->assertStatus(409);
        $this->deleteJson('/api/seasons/'.$own['season']->id)->assertStatus(409);
        $this->putJson('/api/seasons/'.$own['season']->id, ['name' => 'Changed', 'plot_id' => $own['plot']->id, 'crop_id' => $own['user']->crops()->create(['name' => 'Wheat'])->id, 'start_date' => '2026-01-01', 'status' => 'active'])->assertStatus(409);
        $this->deleteJson('/api/sales/'.$id)->assertNoContent();
        $this->postJson('/api/sales', array_replace($sale, ['quantity' => '10']))->assertCreated();
    }

    public function test_numeric_validation_and_reports_separate_currency_units_and_years(): void
    {
        $own = $this->setupFarmer();
        $this->signIn($own);
        $payloads = $this->payloads($own);
        foreach (['expenses' => 'amount', 'harvests' => 'quantity', 'sales' => 'quantity'] as $module => $field) {
            $this->postJson('/api/'.$module, array_replace($payloads[$module], [$field => -1]))->assertUnprocessable();
            $this->postJson('/api/'.$module, array_replace($payloads[$module], [$field => '0.00001']))->assertUnprocessable();
            $this->postJson('/api/'.$module, array_replace($payloads[$module], ['date' => 'invalid']))->assertUnprocessable();
        }
        $this->postJson('/api/expenses', $payloads['expenses'])->assertCreated();
        $this->postJson('/api/expenses', array_replace($payloads['expenses'], ['amount' => '0.20', 'currency' => 'USD']))->assertCreated();
        $this->postJson('/api/expenses', array_replace($payloads['expenses'], ['amount' => '50', 'date' => '2025-01-01']))->assertCreated();
        $this->postJson('/api/sales', $payloads['sales'])->assertCreated();
        $this->postJson('/api/harvests', array_replace($payloads['harvests'], ['unit' => 'tonne']))->assertCreated();
        $report = $this->getJson('/api/reports?year=2026&farm_id='.$own['farm']->id)->assertOk()->json();
        $totals = collect($report['totals'])->keyBy('currency');
        $this->assertSame('10.10', $totals['INR']['expenses']);
        $this->assertSame('12.63', $totals['INR']['sales']);
        $this->assertSame('2.53', $totals['INR']['net']);
        $this->assertSame('-0.20', $totals['USD']['net']);
        $harvests = collect($report['harvests'])->keyBy('unit');
        $this->assertSame('10.0000', $harvests['kg']['quantity']);
        $this->assertSame('5.5000', $harvests['tonne']['quantity']);
        $this->getJson('/api/reports?year=2025')->assertOk()->assertJsonPath('totals.0.expenses', '50.00');
    }

    public function test_actual_record_dates_cannot_be_in_the_future_on_create_or_update(): void
    {
        $own = $this->setupFarmer();
        $this->signIn($own);
        // It is already October 7 in India while the UTC date is October 6.
        $this->travelTo(Carbon::parse('2026-10-06 20:00:00', 'UTC'));
        foreach ($this->payloads($own) as $module => $payload) {
            if ($module === 'activities') {
                continue;
            }
            $today = array_replace($payload, ['date' => '2026-10-07']);
            $id = $this->postJson('/api/'.$module, $today)->assertCreated()->json('id');
            $tomorrow = array_replace($payload, ['date' => '2026-10-08']);
            $this->postJson('/api/'.$module, $tomorrow)->assertUnprocessable()->assertJsonValidationErrors('date');
            $this->putJson('/api/'.$module.'/'.$id, $tomorrow)->assertUnprocessable()->assertJsonValidationErrors('date');
        }
        $this->postJson('/api/activities', array_replace($this->payloads($own)['activities'], ['date' => '2026-10-08']))->assertCreated();
    }
}
