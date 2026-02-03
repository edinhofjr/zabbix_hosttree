<?php

namespace Modules\HostTree\Actions;

use CController;
use CControllerResponseData;
use CRoleHelper;
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

        $this->setResponse(
            new CControllerResponseData(
                ["status" => "success",
                 "host_groups" => $hostGroups]
            )
        );
    }
}