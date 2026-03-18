<?php

namespace Modules\HostTree\Actions;

use CController;
use CControllerResponseData;
use CRoleHelper;
use Modules\HostTree\Classes\HostTreeNodeFactory;
use Modules\HostTree\Services\HostTreeAPIService;

class HostTreeViewRefreshController extends CController {
    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'groupids' => 'array_id',
            'filter_name' => 'string',
            'filter_custom_time' => 'in 1,0',
            'filter_show_counter' => 'in 1,0',
            'page' => 'ge 1',
        ];

        return $this->validateInput($fields);
    }

    protected function checkPermissions(): bool {
        return $this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS);
    }

    protected function doAction(): void {
        $hostGroups = HostTreeAPIService::getAllHostGroups();
        $hostGroupsById = [];

        foreach ($hostGroups as $hostGroup) {
            $hostGroupsById[(string) $hostGroup['groupid']] = $hostGroup;
        }

        $selectedGroupIds = $this->sanitizeGroupIds($this->getInput('groupids', []), $hostGroupsById);
        $filteredHostGroups = $this->filterHostGroupsByIds($hostGroups, $selectedGroupIds);

        $hostGroupIds = array_map(static fn(array $hostGroup): string => (string) $hostGroup['groupid'], $filteredHostGroups);
        $hostData = HostTreeAPIService::getHostCountsAndProblemsByHostGroupIds($hostGroupIds);
        $hostGroupCounters = $hostData['counts'];
        $hostGroupProblemCounters = $hostData['problems'];

        $tree = [];

        foreach ($filteredHostGroups as $hostGroup) {
            $groupId = (string) $hostGroup['groupid'];
            $hostCount = (int) ($hostGroupCounters[$groupId] ?? 0);

            if ($hostCount === 0) {
                continue;
            }

            $groupName = sprintf('%s (%d)', $hostGroup['name'], $hostCount);

            $tree[] = HostTreeNodeFactory::createNode(
                $groupId,
                $groupName,
                0,
                true,
                true,
                false,
                $hostGroupProblemCounters[$groupId] ?? [],
                null,
                null,
                [],
                'group'
            );
        }

        $this->setResponse(new CControllerResponseData([
            'status' => 'success',
            'data' => $tree,
            'severity_meta' => HostTreeNodeFactory::createSeverityMeta(),
        ]));
    }

    private function filterHostGroupsByIds(array $hostGroups, array $selectedGroupIds): array {
        if ($selectedGroupIds === []) {
            return $hostGroups;
        }

        $selectedLookup = array_flip($selectedGroupIds);
        $filtered = [];

        foreach ($hostGroups as $hostGroup) {
            $groupId = (string) $hostGroup['groupid'];

            if (!array_key_exists($groupId, $selectedLookup)) {
                continue;
            }

            $filtered[] = $hostGroup;
        }

        return $filtered;
    }

    private function sanitizeGroupIds(array $groupIds, array $hostGroupsById): array {
        $validateAgainstAvailableGroups = ($hostGroupsById !== []);
        $sanitizedGroupIds = [];

        foreach ($groupIds as $groupId) {
            $groupId = (string) $groupId;

            if (!ctype_digit($groupId)) {
                continue;
            }

            if ($validateAgainstAvailableGroups && !array_key_exists($groupId, $hostGroupsById)) {
                continue;
            }

            $sanitizedGroupIds[$groupId] = true;
        }

        return array_keys($sanitizedGroupIds);
    }
}
