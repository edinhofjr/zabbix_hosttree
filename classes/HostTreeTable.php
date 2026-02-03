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
            //make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], ''),
            make_sorting_header(_('Name'), 'name', 'name', 'DESC', ''),
            (new CColHeader(_('Interface'))),
            (new CColHeader(_('Availability'))),
            (new CColHeader(_('Tags'))),
            make_sorting_header(_('Status'), 'status', 'name', 'DESC', ''),
            (new CColHeader(_('Latest data'))),
            (new CColHeader(_('Problems'))),
            (new CColHeader(_('Graphs'))),
            (new CColHeader(_('Dashboards'))),
            (new CColHeader(_('Web')))
        ]);

        $this->setAttribute("id", "host_tree");
    }
}
