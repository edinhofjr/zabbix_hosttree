<?php

namespace Modules\HostTree\Classes;

use CTableInfo;
use CColHeader;
use Modules\HostTree\Classes\HostTreeTableRow;

class HostTreeTable extends CTableInfo {
    public function __construct()
    {
        parent::__construct();
        $this->setHeader([
            (new CColHeader(_('Name'))),
            (new CColHeader(_('Problems')))
            // (new CColHeader(_('Interface'))),
            // (new CColHeader(_('Availability'))),
            // (new CColHeader(_('Tags'))),
            // (new CColHeader(_('Status'))),
            // (new CColHeader(_('Latest data'))),
            // (new CColHeader(_('Problems'))),
            // (new CColHeader(_('Graphs'))),
            // (new CColHeader(_('Dashboards'))),
            // (new CColHeader(_('Web')))
        ]);

        $this->setAttribute("id", "host_tree");
    }
}
