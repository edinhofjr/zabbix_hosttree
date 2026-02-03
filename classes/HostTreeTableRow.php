<?php

namespace Modules\HostTree\Classes;
use Modules\HostTree\Classes\HTMLHelper;
use CRow;
use CCol;
use CSimpleButton;
use CSpan;

class HostTreeTableRow extends CRow {
    public function __construct(bool $wrapper = false, int $level = 0, string $name, $id) {
        parent::__construct();

        $toogle_tag = ""; 
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
                ->addItem(bold($name))
        );

        $this->setAttribute("node_id", $id);
    }
}