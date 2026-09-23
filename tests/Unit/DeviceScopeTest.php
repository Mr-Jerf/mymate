<?php

namespace Tests\Unit;

use App\Support\DeviceScope;
use PHPUnit\Framework\TestCase;

class DeviceScopeTest extends TestCase
{
    public function test_null_and_empty_scopes_remain_fleet_wide(): void
    {
        $this->assertNull(DeviceScope::resolve(null));
        $this->assertNull(DeviceScope::resolve([]));
        $this->assertNull(DeviceScope::resolve(['type' => 'all']));
    }

    public function test_malformed_scope_fails_closed(): void
    {
        $this->assertSame([], DeviceScope::resolve(['unexpected' => true]));
        $this->assertSame([], DeviceScope::resolve('bad'));
        $this->assertSame([], DeviceScope::resolve(['type' => 'unknown']));
        $this->assertSame([], DeviceScope::resolve(['type' => 'devices', 'device_ids' => 'bad']));
        $this->assertSame([], DeviceScope::resolve(['type' => 'map', 'map_id' => ['bad']]));
        $this->assertSame([], DeviceScope::resolve(['type' => 'device_type', 'device_type' => ['bad']]));
    }
}
