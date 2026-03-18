<?php

/**
 * @var CView $this
 */

$bootstrap = [
    'payload' => $data,
    'ui' => [
        'display_none_class' => ZBX_STYLE_DISPLAY_NONE,
        'treeview_class' => ZBX_STYLE_TREEVIEW,
        'arrow_right_class' => ZBX_STYLE_ARROW_RIGHT,
        'arrow_down_class' => ZBX_STYLE_ARROW_DOWN,
        'nowrap_class' => ZBX_STYLE_NOWRAP,
        'problem_icon_list_class' => ZBX_STYLE_PROBLEM_ICON_LIST,
        'problem_icon_list_item_class' => ZBX_STYLE_PROBLEM_ICON_LIST_ITEM,
        'problem_icon_link_class' => ZBX_STYLE_PROBLEM_ICON_LINK,
        'link_action_class' => ZBX_STYLE_LINK_ACTION
    ],
    'endpoints' => [
        'hosttree_data' => 'zabbix.php?action=hosttree.data',
        'hosttree_view_refresh' => 'zabbix.php?action=hosttree.view.refresh',
        'hosttree_acknowledge' => 'zabbix.php?action=hosttree.acknowledge'
    ]
];
?>
<script type="text/javascript">
    window.hosttreeBootstrap = <?= json_encode($bootstrap) ?>;
</script>
