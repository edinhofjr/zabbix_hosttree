<?php

namespace Modules\HostTree\Classes;
use Modules\HostTree\Classes\HTMLHelper;
use CRow;
use CCol;
use CSimpleButton;
use CSpan;

class HostTreeTableRow extends CRow {
    public function __construct(bool $wrapper = false, int $level = 0, string $name) {
        parent::__construct();

        if ($wrapper) {
            $toogle_tag = (new CSimpleButton())
                ->addClass("wrapper-toggle")
                ->addClass(ZBX_STYLE_TREEVIEW)
                ->addItem(
                    (new CSpan())->addClass(ZBX_STYLE_ARROW_RIGHT)
                );
        }

        $this->addItem(
            (new CCol())
                ->addItem(HTMLHelper::NBSP($level * 4))
                ->addItem($toogle_tag ?? null)
                ->addItem(HTMLHelper::NBSP(1))
                ->addItem(bold($name))
        );
    }
}