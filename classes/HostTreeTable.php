<?php

namespace Modules\HostTree\Classes;

use CTableInfo;
use CColHeader;

class HostTreeTable extends CTableInfo {
    public function __construct()
    {
        parent::__construct();
        $this->setHeader([
            (new CColHeader(_('Name'))),
            (new CColHeader(_('Problems')))
        ]);

        $this->setAttribute("id", "host_tree");
    }
}
