<?php

namespace Modules\HostTree\Classes;

use CTableInfo;
use CColHeader;

class HostTreeTable extends CTableInfo {
    public function __construct()
    {
        parent::__construct();
        $this->setHeader([
            (new CColHeader(_('Host'))),
            (new CColHeader(_('Description'))),
            (new CColHeader(_('Interface'))),
            (new CColHeader(_('Problems')))
        ]);

        $this->setAttribute("id", "host_tree");
    }
}
