<?php

namespace Modules\HostTree\Classes;
use Modules\HostTree\Classes\HTMLHelper;
use CRow;
use CCol;
use CSimpleButton;
use CSpan;
use CMenuPopupHelper;
use CLinkAction;

class HostTreeTableRow extends CRow {
    public function __construct(bool $wrapper = false, int $level = 0, string $name, string $id, bool $popup = false) {
        parent::__construct();

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

        $this->setAttribute("node_id", $id);
    }
}