<?php

namespace Modules\HostTree\Classes;
use Modules\HostTree\Classes\HTMLHelper;
use CRow;
use CCol;
use CDiv;
use CSimpleButton;
use CSpan;
use CLink;
use CUrl;
use CSeverityHelper;
use CMenuPopupHelper;
use CLinkAction;

class HostTreeTableRow extends CRow {
    public function __construct(
        bool $wrapper = false,
        int $level = 0,
        string $name,
        string $id,
        bool $popup = false,
        array $problemCountsBySeverity = [],
        ?string $problemsHostId = null
    ) {
        parent::__construct();

        $toogle_tag = null;

        if ($wrapper) {
            $toogle_tag = (new CSimpleButton())
                ->addClass("wrapper-toggle")
                ->addClass(ZBX_STYLE_TREEVIEW)
                ->setAttribute("node_id", $id)
                ->addItem(
                    (new CSpan())->addClass(ZBX_STYLE_ARROW_RIGHT)
                );
        }

        $this->addItem(
            (new CCol())
                ->addItem(HTMLHelper::NBSP($level * 6))
                ->addItem($toogle_tag)
                ->addItem(HTMLHelper::NBSP(1))
                ->addItem($popup ? 
                    (new CLinkAction($name))->setMenuPopup(CMenuPopupHelper::getHost($id)) : 
                bold($name))
        );

        $problemsCol = (new CCol())->addClass(ZBX_STYLE_NOWRAP);
        $problemsList = (new CDiv())->addClass(ZBX_STYLE_PROBLEM_ICON_LIST);
        $hasProblems = false;

        foreach ($problemCountsBySeverity as $severity => $problemCount) {
            if ((int) $problemCount <= 0) {
                continue;
            }

            $hasProblems = true;
            $problemsList->addItem(
                (new CSpan((string) $problemCount))
                    ->addClass(ZBX_STYLE_PROBLEM_ICON_LIST_ITEM)
                    ->addClass(CSeverityHelper::getStatusStyle((int) $severity))
                    ->setAttribute('title', CSeverityHelper::getName((int) $severity))
            );
        }

        if (!$hasProblems) {
            $problemsList->addItem('0');
        }

        if ($problemsHostId !== null && $hasProblems) {
            $problemsCol->addItem(
                (new CLink('', (new CUrl('zabbix.php'))
                    ->setArgument('action', 'problem.view')
                    ->setArgument('hostids', [$problemsHostId])
                    ->setArgument('filter_set', '1')
                ))
                    ->addClass(ZBX_STYLE_PROBLEM_ICON_LINK)
                    ->addItem($problemsList)
            );
        }
        else {
            $problemsCol->addItem($problemsList);
        }

        $this->addItem($problemsCol);

        $this->setAttribute("node_id", $id);
    }
}
