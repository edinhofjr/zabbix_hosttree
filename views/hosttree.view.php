<?php
/**
 * @var CView $this
 * @var Modules\HostTree\Classes\Dto\HostTreeControllerResponse $data
 */

$this->addJsFile('layout.mode.js');
$this->addJsFile('items.js');

$this->setLayoutMode(ZBX_LAYOUT_NORMAL);
$this->addJsFile('class.tabfilter.js');
$this->addJsFile('class.tabfilteritem.js');
$this->addJsFile('multiselect.js');

$page = (new CHtmlPage())
    ->setTitle(_('Host Tree'));

$viewData = (array) $data;

$filter = (new CTabFilter())
    ->setId('monitoring_hosttree_filter')
    ->setOptions($viewData['tabfilter_options'])
    ->addTemplate(new CPartial($viewData['filter_view'], $viewData['filter_defaults']));

foreach ($viewData['filter_tabs'] as $tab) {
    $tab['tab_view'] = $viewData['filter_view'];
    $filter->addTemplatedTab($tab['filter_name'], $tab);
}

$viewData['filter_options'] = $filter->options;

$page->addItem($filter);
$form = new CPartial("module.monitoring.hosttree.view.html", $viewData);
$page->addItem($form);

$page->show();
