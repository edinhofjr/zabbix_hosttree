<?php

namespace Modules\HostTree\Actions;

use CController;
use CControllerResponseData;
use Modules\HostTree\Classes\HostTreeTableRow;
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
        return true;
    }

    protected function doAction()
    {
        $hostGroupIds = $this->getInput("hostgroup_id", null);
        $hostGroupIds = explode(",", $hostGroupIds);

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

        foreach ($hostTree as $groupName => $hosts) {
            ++$hgacc;

            $groupId = $hostGroupIds[0] . '_' . $hgacc;
            $groupLabel = sprintf('%s (%d)', (string) $groupName, count($hosts));
            $children = [];
            $pacc = 1;
            $groupProblemCount = $this->createEmptyProblemCounters();

            foreach ($hosts as $hostData) {
                $hostId = $groupId . '_' . $pacc++;
                $hostProblemCount = $problemCountsByHostId[(string) $hostData['hostid']]
                    ?? $this->createEmptyProblemCounters();

                foreach ($hostProblemCount as $severity => $problemCount) {
                    $groupProblemCount[$severity] += $problemCount;
                }

                $children[] = [
                    'id' => $hostId,
                    'html' => (new HostTreeTableRow(
                        false,
                        2,
                        $hostData['name'],
                        $hostData['hostid'],
                        true,
                        $hostProblemCount,
                        (string) $hostData['hostid']
                    ))->toString(),
                    'children' => []
                ];
            }

            $tree[] = [
                'id' => $groupId,
                'html' => (new HostTreeTableRow(
                    $hosts !== [],
                    1,
                    $groupLabel,
                    $groupId,
                    false,
                    $groupProblemCount
                ))->toString(),
                'children' => $children
            ];
        }
        $this->setResponse(
            new CControllerResponseData([
                "status" => "ok",
                "data" => $tree,
                "hostgroup" => HostTreeAPIService::getAllHostGroups()
            ])
        );
    }

    private function createEmptyProblemCounters(): array
    {
        $problemCounters = [];

        for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
            $problemCounters[$severity] = 0;
        }

        return $problemCounters;
    }
}
