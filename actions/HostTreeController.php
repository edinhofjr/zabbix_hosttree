<?php

namespace Modules\HostTree\Actions;

use CCsrfTokenHelper;
use CController;
use CControllerResponseData;
use CRoleHelper;
use Modules\HostTree\Classes\HostTreeNodeFactory;
use Modules\HostTree\Services\HostTreeAPIService;

class HostTreeController extends CController {
    protected function init(): void {
		$this->disableCsrfValidation();
	}

    protected function checkInput(): bool {
        $fields = [
            'groupids' => 'array_id',
            'filter_reset' => 'in 1'
        ];

        return $this->validateInput($fields);
    }

    protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS);
	}

    protected function doAction() {
        $hostGroups = HostTreeAPIService::getAllHostGroups();
        $selectedGroupIds = [];

        if (!$this->hasInput('filter_reset')) {
            $inputGroupIds = $this->getInput('groupids', []);

            if (is_array($inputGroupIds)) {
                foreach ($inputGroupIds as $groupId) {
                    $groupId = (string) $groupId;

                    if (!ctype_digit($groupId)) {
                        continue;
                    }

                    $selectedGroupIds[] = $groupId;
                }

                $selectedGroupIds = array_values(array_unique($selectedGroupIds));
            }
        }

        $filteredHostGroups = $this->filterHostGroupsByIds($hostGroups, $selectedGroupIds);
        $groupsMultiselect = $this->buildGroupsMultiselectData($hostGroups, $selectedGroupIds);

        $hostGroupIds = array_map(static fn(array $hostGroup): string => (string) $hostGroup['groupid'], $filteredHostGroups);
        $hostGroupCounters = HostTreeAPIService::getHostCountsByHostGroupIds($hostGroupIds);
        $hostGroupProblemCounters = HostTreeAPIService::getProblemCountsByHostGroupIdsBySeverity($hostGroupIds);

        $nodes = [];
        foreach ($filteredHostGroups as $hostGroup) {
            $groupId = (string) $hostGroup['groupid'];
            $hostCount = (int) ($hostGroupCounters[$groupId] ?? 0);

            if ($hostCount === 0) {
                continue;
            }

            $groupName = sprintf(
                '%s (%d)',
                $hostGroup['name'],
                $hostCount
            );

            $nodes[] = HostTreeNodeFactory::createNode(
                $groupId,
                $groupName,
                0,
                $hostCount > 0,
                true,
                false,
                $hostGroupProblemCounters[$groupId] ?? []
            );
        }

        $filterTab = [
            'filter_name' => '',
            'groupids' => $selectedGroupIds,
            'filter_show_counter' => 0,
            'filter_custom_time' => 0,
            'filter_view_data' => [
                'groups_multiselect' => $groupsMultiselect
            ]
        ];
        $filterTab['filter_src'] = $filterTab;

        $this->setResponse(
            new CControllerResponseData(
                [
                    'status' => 'success',
                    'nodes' => $nodes,
                    'severity_meta' => HostTreeNodeFactory::createSeverityMeta(),
                    'filter_view' => 'module.monitoring.hosttree.filter',
                    'filter_defaults' => [
                        'filter_name' => '',
                        'groupids' => [],
                        'filter_show_counter' => 0,
                        'filter_custom_time' => 0,
                        'filter_view_data' => [
                            'groups_multiselect' => []
                        ]
                    ],
                    'filter_tabs' => [$filterTab],
                    'tabfilter_options' => [
                        'idx' => 'web.monitoring.hosttree.filter',
                        'selected' => 0,
                        'support_custom_time' => 0,
                        'expanded' => true,
                        'page' => 1,
                        'csrf_token' => CCsrfTokenHelper::get('tabfilter')
                    ]
                ]
            )
        );
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

    private function buildGroupsMultiselectData(array $hostGroups, array $selectedGroupIds): array {
        if ($selectedGroupIds === []) {
            return [];
        }

        $selectedLookup = array_flip($selectedGroupIds);
        $groupsMultiselect = [];

        foreach ($hostGroups as $hostGroup) {
            $groupId = (string) $hostGroup['groupid'];

            if (!array_key_exists($groupId, $selectedLookup)) {
                continue;
            }

            $groupsMultiselect[] = [
                'id' => $groupId,
                'name' => $hostGroup['name']
            ];
        }

        return $groupsMultiselect;
    }
}
