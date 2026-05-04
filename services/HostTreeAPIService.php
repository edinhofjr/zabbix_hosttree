<?php

namespace Modules\HostTree\Services;

use API;

class HostTreeAPIService {
    public const OUTROS_BUCKET = 'outros';

    public static function getAllHostGroups(): array {
        return API::HostGroup()->get([
            'output' => ['groupid', 'name'],
            'sortfield' => 'name',
        ]);
    }

    /**
     * Fetches host counts and problem counts per host group in a single host API call.
     *
     * @return array{counts: array<string,int>, problems: array<string,array<int,int>>}
     */
    public static function getHostCountsAndProblemsByHostGroupIds(array $hostGroupIds): array {
        $empty = ['counts' => [], 'problems' => []];

        if ($hostGroupIds === []) {
            return $empty;
        }

        $hostGroupIds = array_values(array_unique(array_map('strval', $hostGroupIds)));
        $countByGroupId = array_fill_keys($hostGroupIds, 0);
        $problemsByGroupId = [];

        foreach ($hostGroupIds as $id) {
            $problemsByGroupId[$id] = self::createEmptyProblemCounters();
        }

        $hosts = API::Host()->get([
            'output' => ['hostid'],
            'groupids' => $hostGroupIds,
            'selectHostGroups' => ['groupid'],
            'preservekeys' => true,
        ]);

        if ($hosts === []) {
            return ['counts' => $countByGroupId, 'problems' => $problemsByGroupId];
        }

        foreach ($hosts as $host) {
            foreach ($host['hostgroups'] as $hostgroup) {
                $groupid = (string) $hostgroup['groupid'];

                if (array_key_exists($groupid, $countByGroupId)) {
                    $countByGroupId[$groupid]++;
                }
            }
        }

        $hostProblemCounts = self::getProblemCountsByHostIdsBySeverity(array_keys($hosts));

        foreach ($hosts as $host) {
            $hostId = (string) $host['hostid'];
            $problemCountsBySeverity = $hostProblemCounts[$hostId] ?? self::createEmptyProblemCounters();

            foreach ($host['hostgroups'] as $hostgroup) {
                $groupId = (string) $hostgroup['groupid'];

                if (!array_key_exists($groupId, $problemsByGroupId)) {
                    continue;
                }

                foreach ($problemCountsBySeverity as $severity => $problemCount) {
                    $problemsByGroupId[$groupId][$severity] += $problemCount;
                }
            }
        }

        return ['counts' => $countByGroupId, 'problems' => $problemsByGroupId];
    }

    public static function getProblemCountsByHostIdsBySeverity(array $hostIds): array {
        if ($hostIds === []) {
            return [];
        }

        $hostIds = array_values(array_unique(array_map('strval', $hostIds)));
        $countByHostId = [];

        foreach ($hostIds as $hostId) {
            $countByHostId[$hostId] = self::createEmptyProblemCounters();
        }

        $triggers = API::Trigger()->get([
            'output' => [],
            'selectHosts' => ['hostid'],
            'hostids' => $hostIds,
            'skipDependent' => true,
            'monitored' => true,
            'preservekeys' => true,
        ]);

        if ($triggers === []) {
            return $countByHostId;
        }

        $problems = API::Problem()->get([
            'output' => ['eventid', 'objectid', 'severity'],
            'source' => EVENT_SOURCE_TRIGGERS,
            'object' => EVENT_OBJECT_TRIGGER,
            'objectids' => array_keys($triggers),
            'symptom' => false,
        ]);

        foreach ($problems as $problem) {
            $triggerId = (string) $problem['objectid'];

            if (!array_key_exists($triggerId, $triggers)) {
                continue;
            }

            foreach ($triggers[$triggerId]['hosts'] as $triggerHost) {
                $hostId = (string) $triggerHost['hostid'];

                if (!array_key_exists($hostId, $countByHostId)) {
                    continue;
                }

                $severity = (int) $problem['severity'];

                if (!array_key_exists($severity, $countByHostId[$hostId])) {
                    continue;
                }

                $countByHostId[$hostId][$severity]++;
            }
        }

        return $countByHostId;
    }

    public static function getHostTreeByHostGroupId(array $hostGroupId): array {
        $hosts = self::getAllHostsByHostGroupId($hostGroupId);
        $hostTree = [];
        $otherHosts = [];

        foreach ($hosts as $host) {
            $bucket = self::getPontoBucket($host['tags'] ?? []);

            if ($bucket === null) {
                $otherHosts[] = $host;
                continue;
            }

            $hostTree[$bucket][] = $host;
        }

        if ($hostTree !== []) {
            ksort($hostTree, SORT_NATURAL | SORT_FLAG_CASE);
        }

        if ($otherHosts !== []) {
            $hostTree[self::OUTROS_BUCKET] = $otherHosts;
        }

        return $hostTree;
    }

    public static function getAllHostsByHostGroupId(array $hostGroupId): array {
        return API::Host()->get([
            'output' => ['hostid', 'name', 'description'],
            'selectTags' => ['tag', 'value'],
            'selectInterfaces' => ['ip', 'dns', 'main', 'useip'],
            'groupids' => $hostGroupId,
            'sortfield' => 'name',
        ]);
    }

    public static function extractMainInterfaceAddress(array $interfaces): ?string {
        foreach ($interfaces as $interface) {
            if ((int) $interface['main'] === 1) {
                $address = ((int) $interface['useip'] === 1) ? $interface['ip'] : $interface['dns'];
                return ($address !== '') ? $address : null;
            }
        }

        if (!empty($interfaces)) {
            $first = $interfaces[0];
            $address = ((int) $first['useip'] === 1) ? $first['ip'] : $first['dns'];
            return ($address !== '') ? $address : null;
        }

        return null;
    }

    private static function getPontoBucket(array $tags): ?string {
        foreach ($tags as $tag) {
            if (($tag['tag'] ?? '') !== 'ponto') {
                continue;
            }

            $value = trim((string) ($tag['value'] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function createEmptyProblemCounters(): array {
        $problemCounters = [];

        for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
            $problemCounters[$severity] = 0;
        }

        return $problemCounters;
    }
}
