<?php

namespace Modules\HostTree\Actions;

use API;
use CController;
use CControllerResponseData;
use CRoleHelper;

class HostTreeAcknowledgeController extends CController {
    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'host_ids' => 'required|array_id',
            'message'  => 'string',
        ];

        return $this->validateInput($fields);
    }

    protected function checkPermissions(): bool {
        return $this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS);
    }

    protected function doAction(): void {
        $hostIds = $this->getInput('host_ids', []);
        $message = trim($this->getInput('message', ''));

        if ($hostIds === []) {
            $this->setResponse(new CControllerResponseData([
                'status' => 'error',
                'message' => 'No hosts provided.',
            ]));
            return;
        }

        $triggers = API::Trigger()->get([
            'output' => [],
            'hostids' => $hostIds,
            'monitored' => true,
            'skipDependent' => true,
            'preservekeys' => true,
        ]);

        if ($triggers === []) {
            $this->setResponse(new CControllerResponseData([
                'status' => 'ok',
                'acknowledged' => 0,
            ]));
            return;
        }

        $problems = API::Problem()->get([
            'output' => ['eventid'],
            'source' => EVENT_SOURCE_TRIGGERS,
            'object' => EVENT_OBJECT_TRIGGER,
            'objectids' => array_keys($triggers),
            'symptom' => false,
        ]);

        if ($problems === []) {
            $this->setResponse(new CControllerResponseData([
                'status' => 'ok',
                'acknowledged' => 0,
            ]));
            return;
        }

        $eventIds = array_column($problems, 'eventid');
        $action = ZBX_PROBLEM_UPDATE_ACKNOWLEDGE;

        if ($message !== '') {
            $action |= ZBX_PROBLEM_UPDATE_MESSAGE;
        }

        $acknowledgeParams = ['eventids' => $eventIds, 'action' => $action];

        if ($message !== '') {
            $acknowledgeParams['message'] = $message;
        }

        $result = API::Event()->acknowledge($acknowledgeParams);

        if ($result === false) {
            $this->setResponse(new CControllerResponseData([
                'status' => 'error',
                'message' => 'Failed to acknowledge events.',
            ]));
            return;
        }

        $this->setResponse(new CControllerResponseData([
            'status' => 'ok',
            'acknowledged' => count($eventIds),
        ]));
    }
}
