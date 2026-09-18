<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_a_site_to_a_state(): void
    {
        $this->actingAsUser();
        $site = Site::factory()->create();

        $this->patchJson("/api/sites/{$site->id}", ['state_code' => 'UT'])
            ->assertOk()
            ->assertJsonPath('data.state_code', 'UT');

        $this->assertDatabaseHas('sites', ['id' => $site->id, 'state_code' => 'UT']);
    }

    public function test_site_state_is_limited_to_supported_states(): void
    {
        $this->actingAsUser();
        $site = Site::factory()->create();

        $this->patchJson("/api/sites/{$site->id}", ['state_code' => 'XX'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['state_code']);
    }

    public function test_non_admin_cannot_assign_a_site_to_a_state(): void
    {
        $this->actingAs(User::factory()->create());
        $site = Site::factory()->create();

        $this->patchJson("/api/sites/{$site->id}", ['state_code' => 'UT'])->assertForbidden();
    }

    public function test_admin_can_read_and_update_status_page_settings(): void
    {
        $this->actingAsUser();
        $this->getJson('/api/settings/status-page')->assertOk()->assertJsonPath('data.brand_name', 'Network Status');
        $this->putJson('/api/settings/status-page', ['brand_name' => 'Example ISP', 'poll_ms' => 60000])->assertOk()->assertJsonPath('data.brand_name', 'Example ISP')->assertJsonPath('data.poll_ms', 60000);
    }

    public function test_status_page_settings_include_custom_public_colors(): void
    {
        $this->actingAsUser();

        $this->putJson('/api/settings/status-page', [
            'color_outage' => '#ff1234',
            'color_degraded' => '#ffaa00',
            'color_maintenance_active' => '#2244ff',
        ])->assertOk()
            ->assertJsonPath('data.color_outage', '#ff1234')
            ->assertJsonPath('data.color_degraded', '#ffaa00')
            ->assertJsonPath('data.color_maintenance_active', '#2244ff');
    }

    public function test_public_status_requires_the_status_api_token(): void
    {
        $this->getJson('/api/public/status')->assertUnauthorized();
        $this->withHeader('X-Status-Api-Key', 'wrong-token')->getJson('/api/public/status')->assertUnauthorized();
        $this->withHeader('X-Status-Api-Key', 'test-token')->getJson('/api/public/status')->assertOk();
    }
    public function test_public_status_rolls_up_monitored_devices_by_state(): void
    {
        $utah = Site::factory()->create(['name' => 'Utah POP', 'state_code' => 'UT']);
        $idaho = Site::factory()->create(['name' => 'Idaho POP', 'state_code' => 'ID']);
        Device::factory()->create(['site_id' => $utah->id, 'status' => 'up', 'monitored' => true]);
        Device::factory()->create(['site_id' => $utah->id, 'status' => 'down', 'monitored' => true]);
        Device::factory()->create(['site_id' => $idaho->id, 'status' => 'up', 'monitored' => true]);

        $this->withHeader('X-Status-Api-Key', 'test-token')->getJson('/api/public/status')
            ->assertOk()
            ->assertJsonPath('data.overall.status', 'degraded')
            ->assertJsonFragment(['state_code' => 'ID', 'status' => 'operational'])
            ->assertJsonFragment(['state_code' => 'UT', 'status' => 'degraded', 'monitored_devices' => 2, 'down_devices' => 1]);
    }

    public function test_public_status_does_not_expose_device_or_site_details(): void
    {
        $site = Site::factory()->create(['name' => 'Secret Internal POP', 'state_code' => 'KS']);
        Device::factory()->create(['site_id' => $site->id, 'name' => 'router-secret', 'status' => 'up']);

        $response = $this->withHeader('X-Status-Api-Key', 'test-token')->getJson('/api/public/status')->assertOk();
        $response->assertJsonMissingPath('data.sites.0.devices');
        $response->assertJsonFragment(['name' => 'Secret Internal POP']);
        $response->assertJsonMissing(['router-secret']);
    }

    public function test_unassigned_sites_do_not_create_a_public_state(): void
    {
        $site = Site::factory()->create(['state_code' => null]);
        Device::factory()->create(['site_id' => $site->id, 'status' => 'down']);

        $this->withHeader('X-Status-Api-Key', 'test-token')->getJson('/api/public/status')
            ->assertOk()
            ->assertJsonCount(0, 'data.sites')
            ->assertJsonMissing(['down_devices' => 1]);
    }

    public function test_unknown_monitoring_does_not_claim_operational_or_perfect_uptime(): void
    {
        $site = Site::factory()->create(['state_code' => 'UT']);
        Device::factory()->create(['site_id' => $site->id, 'status' => 'unknown']);

        $this->withHeader('X-Status-Api-Key', 'test-token')->getJson('/api/public/status')
            ->assertOk()
            ->assertJsonPath('data.overall.status', 'unknown')
            ->assertJsonFragment(['state_code' => 'UT', 'status' => 'unknown', 'uptime_60d' => null]);
    }
}
