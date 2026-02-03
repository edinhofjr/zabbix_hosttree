<?php
/**
 * @var CView $this
 * @var Modules\HostTree\Classes\Dto\HostTreeControllerResponse $data
 */

use Modules\HostTree\Classes\HostGroup;
use Modules\HostTree\Classes\Mapper;


$hostGroups = Mapper::DecodeTo($data["host_groups"], HostGroup::class);

$this->setLayoutMode(ZBX_LAYOUT_NORMAL);

$page = (new CHtmlPage())
    ->setTitle(_('Host Tree'));

$form = new CPartial("module.monitoring.hosttree.view.html", (array) $data);

$page->addItem($form);

$page->show();

(new CScriptTag())
    ->setOnDocumentReady()
    ->show();
