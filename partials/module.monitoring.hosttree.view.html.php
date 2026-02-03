<?php

use Modules\HostTree\Classes\HostTreeTable;
use Modules\HostTree\Classes\HostTreeTableRow;

$table = new HostTreeTable();

$cc = 0; 

foreach ($data["host_groups"] as $hostGroup) {
    $table->addRow(
        new HostTreeTableRow(
            true, 
            0,
            $hostGroup["name"],
            $hostGroup["groupid"]
        )
    );
}

echo $table;

$this->includeJsFile("hosttree.view.js.php");