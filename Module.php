<?php 

namespace Modules\HostTree;

use APP;
use CMenuItem;

class Module extends \Zabbix\Core\CModule {
    public function init() : void {
        APP::Component()->get('menu.main')
			->findOrAdd(_('Monitoring'))
				->getSubmenu()
					->insertAfter('Hosts', (new \CMenuItem(_('Host Tree')))
						->setAction('hosttree.view')
					);
    } 
}