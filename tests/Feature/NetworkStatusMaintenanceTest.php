<?php

namespace Tests\Feature;

use App\Models\MaintenanceWindow;
use App\Models\Device;
use App\Models\Outage;
use App\Models\OutageUpdate;
use App\Models\Site;
use App\Models\StatusIncident;
use App\Models\StatusIncidentUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkStatusMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_window_accepts_overview_and_description(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/maintenance-windows', [
            'name' => 'Core router upgrade',
            'description' => 'We are upgrading our core router in Utah. Customers may experience brief downtime.',
            'starts_at' => now()->subMinute()->toISOString(),
            'ends_at' => now()->addHour()->toISOString(),
            'scope' => ['type' => 'all'],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Core router upgrade')
            ->assertJsonPath('data.description', 'We are upgrading our core router in Utah. Customers may experience brief downtime.');
    }

    public function test_public_status_excludes_malformed_scope_notice(): void
    {
        MaintenanceWindow::factory()->create([
            'name' => 'Internal malformed scope',
            'description' => 'Must never be public.',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'scope' => ['unexpected' => true],
        ]);

        $this->withHeader('X-Status-Api-Key', 'test-token')->getJson('/api/public/status')
            ->assertOk()
            ->assertJsonMissing(['overview' => 'Internal malformed scope', 'description' => 'Must never be public.']);
    }

    public function test_operator_can_post_outage_status_update(): void
    {
        $this->actingAsUser();
        $outage = Outage::factory()->create();

        $this->postJson("/api/outages/{$outage->id}/updates", [
            'message' => 'Field technicians are checking the upstream handoff now.',
        ])->assertCreated()
            ->assertJsonPath('data.message', 'Field technicians are checking the upstream handoff now.');

        $this->assertDatabaseHas('outage_updates', [
            'outage_id' => $outage->id,
            'message' => 'Field technicians are checking the upstream handoff now.',
        ]);
    }
    public function test_non_admin_cannot_post_outage_status_update(): void
    {
        $this->actingAs(User::factory()->create());
        $outage = Outage::factory()->create();

        $this->postJson("/api/outages/{$outage->id}/updates", ['message' => 'Should be rejected'])
            ->assertForbidden();
    }
    public function test_public_status_includes_state_outage_status_feed_without_device_details(): void
    {
        $site = Site::factory()->create(['state_code' => 'UT']);
        $device = Device::factory()->create(['site_id' => $site->id, 'status' => 'down', 'monitored' => true]);
        $outage = Outage::factory()->create(['device_id' => $device->id, 'ended_at' => null]);
        $incident = StatusIncident::create(['site_id' => $site->id, 'state_code' => 'UT', 'severity' => 'outage', 'status' => 'investigating', 'summary' => 'Service outage', 'started_at' => now()->subMinute()]);
        $outage->update(['status_incident_id' => $incident->id]);
        StatusIncidentUpdate::create(['status_incident_id' => $incident->id, 'message' => 'Our team is investigating the service interruption.']);

        $this->withHeader('X-Status-Api-Key', 'test-token')->getJson('/api/public/status')
            ->assertOk()
            ->assertJsonPath('data.status_feed.0.state', 'UT')
            ->assertJsonPath('data.status_feed.0.updates.0.message', 'Our team is investigating the service interruption.')
            ->assertJsonMissing(['device_name' => $device->name]);
    }
    public function test_public_status_excludes_unmonitored_device_outage(): void
    {
        $site = Site::factory()->create(['state_code' => 'UT']);
        $device = Device::factory()->create(['site_id' => $site->id, 'status' => 'down', 'monitored' => false]);
        Outage::factory()->create(['device_id' => $device->id, 'ended_at' => null]);

        $this->withHeader('X-Status-Api-Key', 'test-token')->getJson('/api/public/status')
            ->assertOk()
            ->assertJsonPath('data.status_feed', []);
    }
    public function test_public_status_shows_recent_maintenance_and_hides_older_windows(): void
    {
        MaintenanceWindow::factory()->create([
            'name' => 'Recent completed upgrade',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(9),
            'scope' => null,
        ]);
        MaintenanceWindow::factory()->create([
            'name' => 'Expired notice',
            'starts_at' => now()->subDays(31),
            'ends_at' => now()->subDays(30)->subMinute(),
            'scope' => null,
        ]);

        $this->withHeader('X-Status-Api-Key', 'test-token')->getJson('/api/public/status')
            ->assertOk()
            ->assertJsonFragment(['overview' => 'Recent completed upgrade', 'status' => 'completed'])
            ->assertJsonMissing(['overview' => 'Expired notice']);
    }
}
