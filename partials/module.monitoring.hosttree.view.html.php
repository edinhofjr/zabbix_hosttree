<?php

/**
 * @var CPartial $this
 */

use Modules\HostTree\Classes\HostTreeTable;

echo (new HostTreeTable())->addRow(
    new CRow()
);

$this->includeJsFile("hosttree.view.js.php", $data);