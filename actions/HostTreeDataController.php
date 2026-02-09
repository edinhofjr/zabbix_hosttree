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

        $tree = [];
        $hgacc = 0;

        foreach ($hostTree as $groupName => $hosts) {
            ++$hgacc;

            $groupId = $hostGroupIds[0] . '_' . $hgacc;
            $children = [];
            $pacc = 1;

            foreach ($hosts as $hostData) {
                $hostId = $groupId . '_' . $pacc++;

                $children[] = [
                    'id' => $hostId,
                    'html' => (new HostTreeTableRow(
                        false,
                        2,
                        $hostData['name'],
                        $hostData['hostid'],
                        true
                    ))->toString(),
                    'children' => []
                ];
            }

            $tree[] = [
                'id' => $groupId,
                'html' => (new HostTreeTableRow(
                    true,
                    1,
                    $groupName,
                    $groupId
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
}