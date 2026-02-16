<?php

namespace Modules\HostTree\Services;

use API;

class HostTreeAPIService {
    public static function getAllHostGroups() {
        return API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'sortfield' => 'name',
			]);
    }

    public static function getHostCountsByHostGroupIds(array $hostGroupIds): array {
        if ($hostGroupIds === []) {
            return [];
        }

        $hostGroupIds = array_values(array_unique(array_map('strval', $hostGroupIds)));
        $countByGroupId = array_fill_keys($hostGroupIds, 0);

        $hosts = API::Host()->get([
            'output' => ['hostid'],
            'groupids' => $hostGroupIds,
            'selectHostGroups' => ['groupid']
        ]);

        foreach ($hosts as $host) {
            foreach ($host['hostgroups'] as $hostgroup) {
                $groupid = (string) $hostgroup['groupid'];

                if (!array_key_exists($groupid, $countByGroupId)) {
                    continue;
                }

                $countByGroupId[$groupid]++;
            }
        }

        return $countByGroupId;
    }

    public static function getProblemCountsByHostIds(array $hostIds): array {
        $problemCountsByHostIdBySeverity = self::getProblemCountsByHostIdsBySeverity($hostIds);
        $problemCountsByHostId = [];

        foreach ($problemCountsByHostIdBySeverity as $hostId => $problemCountsBySeverity) {
            $problemCountsByHostId[$hostId] = array_sum($problemCountsBySeverity);
        }

        return $problemCountsByHostId;
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
            'preservekeys' => true
        ]);

        if ($triggers === []) {
            return $countByHostId;
        }

        $problems = API::Problem()->get([
            'output' => ['eventid', 'objectid', 'severity'],
            'source' => EVENT_SOURCE_TRIGGERS,
            'object' => EVENT_OBJECT_TRIGGER,
            'objectids' => array_keys($triggers),
            'symptom' => false
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

    public static function getProblemCountsByHostGroupIds(array $hostGroupIds): array {
        $problemCountsByHostGroupIdBySeverity = self::getProblemCountsByHostGroupIdsBySeverity($hostGroupIds);
        $problemCountsByHostGroupId = [];

        foreach ($problemCountsByHostGroupIdBySeverity as $groupId => $problemCountsBySeverity) {
            $problemCountsByHostGroupId[$groupId] = array_sum($problemCountsBySeverity);
        }

        return $problemCountsByHostGroupId;
    }

    public static function getProblemCountsByHostGroupIdsBySeverity(array $hostGroupIds): array {
        if ($hostGroupIds === []) {
            return [];
        }

        $hostGroupIds = array_values(array_unique(array_map('strval', $hostGroupIds)));
        $countByGroupId = [];

        foreach ($hostGroupIds as $hostGroupId) {
            $countByGroupId[$hostGroupId] = self::createEmptyProblemCounters();
        }

        $hosts = API::Host()->get([
            'output' => ['hostid'],
            'groupids' => $hostGroupIds,
            'selectHostGroups' => ['groupid'],
            'preservekeys' => true
        ]);

        if ($hosts === []) {
            return $countByGroupId;
        }

        $hostProblemCounts = self::getProblemCountsByHostIdsBySeverity(array_keys($hosts));

        foreach ($hosts as $host) {
            $hostId = (string) $host['hostid'];
            $problemCountsBySeverity = $hostProblemCounts[$hostId] ?? self::createEmptyProblemCounters();

            foreach ($host['hostgroups'] as $hostgroup) {
                $groupId = (string) $hostgroup['groupid'];

                if (!array_key_exists($groupId, $countByGroupId)) {
                    continue;
                }

                foreach ($problemCountsBySeverity as $severity => $problemCount) {
                    $countByGroupId[$groupId][$severity] += $problemCount;
                }
            }
        }

        return $countByGroupId;
    }

    private static function createEmptyProblemCounters(): array {
        $problemCounters = [];

        for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
            $problemCounters[$severity] = 0;
        }

        return $problemCounters;
    }

    public static function getHostTreeByHostGroupId($hostGroupId) {
        $hosts = self::getAllHostsByHostGroupId($hostGroupId);
        $hostTree = [];
        $otherHosts = [];

        foreach ($hosts as $host) {
            $bucket = self::getPontoBucket($host['tags']);

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
            $hostTree['outros'] = $otherHosts;
        }

        return $hostTree;
    }

    public static function hasTag($key, $value, $tagArray) {
        foreach( $tagArray as $tag) {

        }
    }

    public static function getTag($key, $tagArray) {
        foreach($tagArray as $tag) {

        }
    }

    public static function getAllHostsByHostGroupId($hostGroupId) {
        return API::Host()->get([
				'output' => ['hostid', 'name'],
				'selectTags' => ['tag', 'value'],	
				'groupids' => $hostGroupId,
			]);
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
}
