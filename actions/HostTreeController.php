<?php

namespace Modules\HostTree\Actions;

use CController;
use CControllerResponseData;
use CRoleHelper;
use Modules\HostTree\Classes\HostTreeTableRow;
use Modules\HostTree\Services\HostTreeAPIService;

class HostTreeController extends CController {
    protected function init(): void {
		$this->disableCsrfValidation();
	}

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS);
	}

    protected function doAction() {
        $hostGroups = HostTreeAPIService::getAllHostGroups();
        $hostGroupIds = array_map(static fn(array $hostGroup): string => (string) $hostGroup['groupid'], $hostGroups);
        $hostGroupCounters = HostTreeAPIService::getHostCountsByHostGroupIds($hostGroupIds);
        $hostGroupProblemCounters = HostTreeAPIService::getProblemCountsByHostGroupIdsBySeverity($hostGroupIds);

        $rows = [];
        foreach ($hostGroups as $hostGroup) {
            $groupId = (string) $hostGroup["groupid"];
            $groupName = sprintf(
                '%s (%d)',
                $hostGroup['name'],
                $hostGroupCounters[$groupId] ?? 0
            );

            $rows[] = (new HostTreeTableRow(
                true,
                0,
                $groupName,
                $groupId,
                false,
                $hostGroupProblemCounters[$groupId] ?? []
            ))->toString();
        }

        $this->setResponse(
            new CControllerResponseData(
                [
                    "status" => "success",
                    "html" => $rows
                ]
            )
        );
    }
}
