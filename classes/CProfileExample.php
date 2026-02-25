<?php

namespace Modules\HostTree\Classes;

/**
 * Exemplo simples de persistencia por usuario com CProfile.
 *
 * Uso:
 * $state = CProfileExample::getState();
 * CProfileExample::saveState(['show_infra' => true, 'groupids' => ['2', '5']]);
 * CProfileExample::resetState();
 */
class CProfileExample {
    private const IDX_SHOW_INFRA = 'web.monitoring.hosttree.example.show_infra';
    private const IDX_GROUPIDS = 'web.monitoring.hosttree.example.groupids';

    public static function getState(): array {
        $showInfra = ((int) \CProfile::get(self::IDX_SHOW_INFRA, 1) === 1);
        $groupIds = \CProfile::getArray(self::IDX_GROUPIDS, []);

        if (!is_array($groupIds)) {
            $groupIds = [];
        }

        $groupIds = self::sanitizeGroupIds($groupIds);

        return [
            'show_infra' => $showInfra,
            'groupids' => $groupIds
        ];
    }

    public static function saveState(array $state): void {
        $showInfra = (bool) ($state['show_infra'] ?? true);
        $groupIds = self::sanitizeGroupIds($state['groupids'] ?? []);

        \CProfile::update(self::IDX_SHOW_INFRA, $showInfra ? 1 : 0, \PROFILE_TYPE_INT);
        \CProfile::updateArray(self::IDX_GROUPIDS, $groupIds, \PROFILE_TYPE_ID);
    }

    public static function resetState(): void {
        \CProfile::deleteIdx(self::IDX_SHOW_INFRA);
        \CProfile::deleteIdx(self::IDX_GROUPIDS);
    }

    public static function getDebugData(array $request = []): array {
        $requestGroupIds = [];

        if (array_key_exists('groupids', $request) && is_array($request['groupids'])) {
            $requestGroupIds = array_map(static fn($groupId): string => (string) $groupId, $request['groupids']);
        }

        return [
            'idx' => [
                'show_infra' => self::IDX_SHOW_INFRA,
                'groupids' => self::IDX_GROUPIDS
            ],
            'state' => self::getState(),
            'raw' => [
                'show_infra' => \CProfile::get(self::IDX_SHOW_INFRA, null),
                'groupids' => \CProfile::getArray(self::IDX_GROUPIDS, [])
            ],
            'request' => [
                'groupids' => $requestGroupIds,
                'filter_reset' => array_key_exists('filter_reset', $request),
                'debug_profile' => array_key_exists('debug_profile', $request)
            ],
            'usage' => '?action=hosttree.view&debug_profile=1'
        ];
    }

    private static function sanitizeGroupIds($groupIds): array {
        if (!is_array($groupIds)) {
            return [];
        }

        $sanitized = [];

        foreach ($groupIds as $groupId) {
            $groupId = (string) $groupId;

            if (!ctype_digit($groupId)) {
                continue;
            }

            $sanitized[$groupId] = true;
        }

        return array_keys($sanitized);
    }
}
