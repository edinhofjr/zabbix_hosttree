<?php
/**
 * @var CView $this
 * @var Modules\HostTree\Classes\Dto\HostTreeControllerResponse $data
 */

$this->setLayoutMode(ZBX_LAYOUT_NORMAL);

$page = (new CHtmlPage())
    ->setTitle(_('Host Tree'));

$form = new CPartial("module.monitoring.hosttree.view.html", (array) $data);

$page->addItem($form);

$page->show();

(new CScriptTag())
    ->setOnDocumentReady()
    ->show();
