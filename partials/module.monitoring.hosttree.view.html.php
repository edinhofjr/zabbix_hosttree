<?php

/**
 * @var CPartial $this
 */

use Modules\HostTree\Classes\HostTreeTable;
use Modules\HostTree\Classes\TsImportHelper;

echo (new HostTreeTable())->addRow(
    new CRow()
);

$this->includeJsFile("hosttree.view.js.php", $data);
TsImportHelper::import($this, 'hosttree.view.action.js');
