<?php
/**
 * @var CView $this
 */

$this->setLayoutMode(ZBX_LAYOUT_NORMAL);

$page = (new CHtmlPage())
    ->setTitle(_('Host TSree'));

$form = new CPartial("module.monitoring.hosttree.view.html");

$page->addItem($form);

$page->show();
