<?php
/**
 * @var CPartial $this
 */

$groups_multiselect = [];

if (array_key_exists('filter_view_data', $data)
        && array_key_exists('groups_multiselect', $data['filter_view_data'])) {
    $groups_multiselect = $data['filter_view_data']['groups_multiselect'];
}

$filter_fields = (new CFormGrid())
    ->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
    ->addItem([
        new CLabel(_('Host groups'), 'groupids_#{uniqid}_ms'),
        new CFormField(
            (new CMultiSelect([
                'name' => 'groupids[]',
                'object_name' => 'hostGroup',
                'data' => $groups_multiselect,
                'popup' => [
                    'parameters' => [
                        'srctbl' => 'host_groups',
                        'srcfld1' => 'groupid',
                        'dstfrm' => 'zbx_filter',
                        'dstfld1' => 'groupids_',
                        'with_hosts' => true,
                        'enrich_parent_groups' => true
                    ]
                ],
                'add_post_js' => false
            ]))
                ->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
                ->setId('groupids_#{uniqid}')
        )
    ]);

$template = (new CForm('get'))
    ->setName('zbx_filter')
    ->addItem([
        (new CDiv())
            ->addClass(ZBX_STYLE_TABLE)
            ->addClass(ZBX_STYLE_FILTER_FORMS)
            ->addItem((new CDiv($filter_fields))->addClass(ZBX_STYLE_CELL)),
        (new CSubmitButton())->addClass(ZBX_STYLE_FORM_SUBMIT_HIDDEN),
        (new CVar('filter_name', '#{filter_name}'))->removeId(),
        (new CVar('filter_show_counter', '#{filter_show_counter}'))->removeId(),
        (new CVar('filter_custom_time', '#{filter_custom_time}'))->removeId()
    ]);

if (array_key_exists('render_html', $data)) {
    $template->show();
    return;
}

(new CTemplateTag('filter-monitoring-hosttree'))
    ->setAttribute('data-template', 'module.monitoring.hosttree.filter')
    ->addItem($template)
    ->show();
?>
<script type="text/javascript">
	const template = document.querySelector('[data-template="module.monitoring.hosttree.filter"]');

	function render(data, container) {
		$('[name="filter_new"],[name="filter_update"]').hide();

		$('#groupids_' + data.uniqid, container).multiSelectHelper({
			id: 'groupids_' + data.uniqid,
			object_name: 'hostGroup',
			name: 'groupids[]',
			data: (data.filter_view_data && data.filter_view_data.groups_multiselect) || [],
			objectOptions: {
				with_hosts: 1,
				enrich_parent_groups: 1
			},
			popup: {
				parameters: {
					multiselect: '1',
					srctbl: 'host_groups',
					srcfld1: 'groupid',
					dstfrm: 'zbx_filter',
					dstfld1: 'groupids_' + data.uniqid,
					with_hosts: 1,
					enrich_parent_groups: 1
				}
			}
		});

		this.resetUnsavedState();
		this.on(TABFILTERITEM_EVENT_ACTION, update.bind(this));
	}

	function update(ev) {
		const action = ev.detail.action;

		if (action !== 'filter_apply' && action !== 'filter_update') {
			return;
		}

		const params = this.getFilterParams(false);

		if (!(params instanceof URLSearchParams)) {
			return;
		}

		params.set('action', 'hosttree.view');
		params.delete('page');

		const url = new Curl('zabbix.php');
		url.query = params.toString();
		url.formatArguments();
		window.location.href = url.getUrl();
	}

	template.addEventListener(TABFILTERITEM_EVENT_RENDER, function(ev) {
		render.call(ev.detail, ev.detail._data, ev.detail._content_container);
	});
</script>
