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

        $rows = [];
        foreach ($hostGroups as $hostGroup) {
            $rows[] = (new HostTreeTableRow(
                true,
                0,
                $hostGroup["name"],
                $hostGroup["groupid"]
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