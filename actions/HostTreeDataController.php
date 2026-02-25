<?php

namespace Modules\HostTree\Actions;

use CController;
use CControllerResponseData;
use CMenuPopupHelper;
use CRoleHelper;
use Modules\HostTree\Classes\HostTreeNodeFactory;
use Modules\HostTree\Services\HostTreeAPIService;

class HostTreeDataController extends CController
{
    protected function init(): void
    {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool
    {
        $fields = [
            "hostgroup_id" => "string"
        ];

        return $this->validateInput($fields);
    }

    protected function checkPermissions(): bool
    {
        return $this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS);
    }

    protected function doAction()
    {
        $hostGroupIds = $this->parseHostGroupIds((string) $this->getInput('hostgroup_id', ''));

        if ($hostGroupIds === []) {
            $this->setResponse(
                new CControllerResponseData([
                    'status' => 'ok',
                    'data' => []
                ])
            );

            return;
        }

        $hostTree = HostTreeAPIService::getHostTreeByHostGroupId($hostGroupIds);
        $hostIds = [];

        foreach ($hostTree as $hosts) {
            foreach ($hosts as $hostData) {
                $hostIds[] = (string) $hostData['hostid'];
            }
        }

        $problemCountsByHostId = HostTreeAPIService::getProblemCountsByHostIdsBySeverity($hostIds);

        $tree = [];
        $hgacc = 0;
        $parentId = $hostGroupIds[0];

        foreach ($hostTree as $groupName => $hosts) {
            ++$hgacc;

            $groupId = $parentId.'_'.$hgacc;
            $groupLabel = sprintf('%s (%d)', (string) $groupName, count($hosts));
            $children = [];
            $pacc = 1;
            $groupProblemCount = HostTreeNodeFactory::createEmptyProblemCounters();

            foreach ($hosts as $hostData) {
                $hostId = $groupId.'_'.$pacc++;
                $sourceHostId = (string) $hostData['hostid'];
                $hostProblemCount = $problemCountsByHostId[(string) $hostData['hostid']]
                    ?? HostTreeNodeFactory::createEmptyProblemCounters();

                foreach ($hostProblemCount as $severity => $problemCount) {
                    $groupProblemCount[$severity] += $problemCount;
                }

                $children[] = HostTreeNodeFactory::createNode(
                    $hostId,
                    (string) $hostData['name'],
                    2,
                    false,
                    false,
                    true,
                    $hostProblemCount,
                    $sourceHostId,
                    CMenuPopupHelper::getHost($sourceHostId)
                );
            }

            $tree[] = HostTreeNodeFactory::createNode(
                $groupId,
                $groupLabel,
                1,
                $hosts !== [],
                false,
                false,
                $groupProblemCount,
                null,
                null,
                $children
            );
        }

        $this->setResponse(
            new CControllerResponseData([
                'status' => 'ok',
                'data' => $tree
            ])
        );
    }

    private function parseHostGroupIds(string $hostGroupIds): array
    {
        if ($hostGroupIds === '') {
            return [];
        }

        $validatedHostGroupIds = [];
        $hostGroupIds = explode(',', $hostGroupIds);

        foreach ($hostGroupIds as $hostGroupId) {
            $hostGroupId = trim((string) $hostGroupId);

            if (!ctype_digit($hostGroupId)) {
                continue;
            }

            $validatedHostGroupIds[$hostGroupId] = true;
        }

        return array_keys($validatedHostGroupIds);
    }
}
