<?php

namespace App\Support;

use App\Models\Device;
use App\Models\DeviceMapPosition;

/**
 * Resolve a targeting bag (the `scope` shape shared by alert policies and maintenance
 * windows) to the set of device ids it covers, or `null` for fleet-wide. An explicit
 * scope that matches nothing returns an empty array (covers no devices).
 */
class DeviceScope
{
    /**
     * @param  mixed  $scope
     * @return array<int>|null null = all devices
     */
    public static function resolve(mixed $scope): ?array
    {
        if ($scope !== null && ! is_array($scope)) {
            return [];
        }

        if ($scope === null || $scope === []) {
            return null;
        }

        $type = $scope['type'] ?? null;
        if (! is_string($type)) {
            return [];
        }

        return match ($type) {
            'all' => null,
            'device_type' => is_string($scope['device_type'] ?? null)
                ? Device::where('device_type', $scope['device_type'])->pluck('id')->all()
                : [],
            'map' => is_int($scope['map_id'] ?? null) || (is_string($scope['map_id'] ?? null) && ctype_digit($scope['map_id']))
                ? DeviceMapPosition::where('map_id', (int) $scope['map_id'])->pluck('device_id')->unique()->values()->all()
                : [],
            'devices' => is_array($scope['device_ids'] ?? null)
                ? array_values(array_filter(array_map(
                    static fn (mixed $id): ?int => (is_int($id) || (is_string($id) && ctype_digit($id))) ? (int) $id : null,
                    $scope['device_ids'],
                ), static fn (?int $id): bool => $id !== null))
                : [],
            default => [], // malformed scope covers no devices
        };
    }
}
