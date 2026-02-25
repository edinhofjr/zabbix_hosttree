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
        $pointNodes = [];
        $pointHostCount = 0;
        $pointProblemCount = HostTreeNodeFactory::createEmptyProblemCounters();
        $infraNodes = [];
        $infraHostCount = 0;
        $infraProblemCount = HostTreeNodeFactory::createEmptyProblemCounters();
        $hgacc = 0;
        $parentId = $hostGroupIds[0];

        foreach ($hostTree as $groupName => $hosts) {
            if ($hosts === []) {
                continue;
            }

            ++$hgacc;

            $isOtherGroup = ((string) $groupName === 'outros');
            $groupId = $parentId.'_'.$hgacc;
            $children = [];
            $pacc = 1;
            $groupProblemCount = HostTreeNodeFactory::createEmptyProblemCounters();

            foreach ($hosts as $hostData) {
                $sourceHostId = (string) $hostData['hostid'];
                $hostProblemCount = $problemCountsByHostId[(string) $hostData['hostid']]
                    ?? HostTreeNodeFactory::createEmptyProblemCounters();

                if ($isOtherGroup) {
                    $infraNodes[] = HostTreeNodeFactory::createNode(
                        $parentId.'_infra_'.$pacc++,
                        (string) $hostData['name'],
                        2,
                        false,
                        false,
                        true,
                        $hostProblemCount,
                        $sourceHostId,
                        CMenuPopupHelper::getHost($sourceHostId)
                    );
                    ++$infraHostCount;

                    foreach ($hostProblemCount as $severity => $problemCount) {
                        $infraProblemCount[$severity] += $problemCount;
                    }

                    continue;
                }

                $hostId = $groupId.'_'.$pacc++;

                foreach ($hostProblemCount as $severity => $problemCount) {
                    $groupProblemCount[$severity] += $problemCount;
                }

                $children[] = HostTreeNodeFactory::createNode(
                    $hostId,
                    (string) $hostData['name'],
                    3,
                    false,
                    false,
                    true,
                    $hostProblemCount,
                    $sourceHostId,
                    CMenuPopupHelper::getHost($sourceHostId)
                );
            }

            if ($isOtherGroup) {
                continue;
            }

            $groupNode = HostTreeNodeFactory::createNode(
                $groupId,
                sprintf('%s (%d)', (string) $groupName, count($hosts)),
                2,
                $hosts !== [],
                false,
                false,
                $groupProblemCount,
                null,
                null,
                $children
            );

            $pointNodes[] = $groupNode;
            $pointHostCount += count($hosts);

            foreach ($groupProblemCount as $severity => $problemCount) {
                $pointProblemCount[$severity] += $problemCount;
            }
        }

        if ($pointNodes !== []) {
            array_unshift(
                $tree,
                HostTreeNodeFactory::createNode(
                    $parentId.'_pontos',
                    sprintf('Pontos (%d)', $pointHostCount),
                    1,
                    true,
                    false,
                    false,
                    $pointProblemCount,
                    null,
                    null,
                    $pointNodes
                )
            );
        }

        if ($infraNodes !== []) {
            $tree[] = HostTreeNodeFactory::createNode(
                $parentId.'_infra',
                sprintf('Infra (%d)', $infraHostCount),
                1,
                true,
                false,
                false,
                $infraProblemCount,
                null,
                null,
                $infraNodes
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
