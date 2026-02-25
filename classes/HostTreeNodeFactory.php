<?php

namespace Modules\HostTree\Classes;

use CSeverityHelper;

class HostTreeNodeFactory {
    public static function createNode(
        string $id,
        string $label,
        int $level,
        bool $canExpand,
        bool $needsLoad,
        bool $popup,
        array $problemCountsBySeverity = [],
        ?string $problemHostId = null,
        ?array $menuPopup = null,
        array $children = []
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'level' => $level,
            'can_expand' => $canExpand,
            'needs_load' => $needsLoad,
            'popup' => $popup,
            'problem_host_id' => $problemHostId,
            'menu_popup' => $menuPopup,
            'problem_counts_by_severity' => $problemCountsBySeverity,
            'children' => $children
        ];
    }

    public static function createSeverityMeta(): array {
        $severityMeta = [];

        for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
            $severityMeta[$severity] = [
                'name' => CSeverityHelper::getName($severity),
                'class' => CSeverityHelper::getStatusStyle($severity)
            ];
        }

        return $severityMeta;
    }

    public static function createEmptyProblemCounters(): array {
        $problemCounters = [];

        for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
            $problemCounters[$severity] = 0;
        }

        return $problemCounters;
    }
}
