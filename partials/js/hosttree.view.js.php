<?php

/**
 * @var CView $this
 */
?>

<script type="text/javascript">
    const hostgroup = <?= json_encode($data) ?>;
    const host_tree = {};
    const host_tree_table = $("table#host_tree")[0];
    const host_tree_body = $(host_tree_table).find("tbody");

    const is_virtual_id = (id) => String(id).includes("_")

    const init = () => {
        console.log(hostgroup["html"])

        if (!hostgroup["html"]) {
            return
        }

        host_tree_body.children("tr").remove()
        host_tree_body.append(hostgroup["html"])

    }

    init()

    const Node =
        (el, children, last_fetch, collapsed = true) => {
            if (collapsed) {
                el.addClass('<?= ZBX_STYLE_DISPLAY_NONE ?>');
            } else {
                el.removeClass('<?= ZBX_STYLE_DISPLAY_NONE ?>');
            }

            return {
                el: el,
                children: children,
                last_fetch: last_fetch,
                collapsed: collapsed
            };
        }



    const set_node = (id, node) => {
        host_tree[id] = node;
    }

    const get_node = (id) => {
        if (!(id in host_tree)) {
            host_tree[id] = {
                el: null,
                children: null,
                last_fetch: null,
                collapsed: true
            };
        }
        return host_tree[id];
    };

    const fetch_node = async (id) => {
        const node = get_node(id);

        if (
            (!node.last_fetch /*|| Date.now() - node.last_fetch > FIVE_MINUTES*/) &&
            !is_virtual_id(id)
        ) {
            await fetch_hostgroups(id).done(r => {
                const childrenIds = [];

                for (const collection of r.data) {
                    const groupChildren = [];

                    for (const child of collection.children) {
                        set_node(
                            child.id,
                            Node($(child.html), [], null, true)
                        );
                        groupChildren.push(child.id);
                    }

                    set_node(
                        collection.id,
                        Node($(collection.html), groupChildren, null, true)
                    );

                    childrenIds.push(collection.id);
                }

                node.children = childrenIds;
                node.last_fetch = Date.now();
            });
        }

        return node;
    };


    $("#host_tree")
        .on("click", ".wrapper-toggle",
            async function() {
                const $toggle = $(this);
                const id = $toggle.attr("node_id");

                const node = await fetch_node(id);

                if (node.el == null) {
                    node.el = $(`tr[node_id=${id}]`);
                    append_tree(node);
                }

                const span = $toggle.children("span")[0]
                console.log(span.classList.contains('arrow-right'))

                toggle_classes(span, "arrow-right", "arrow-down")
                toggle(id);
            }
        )

    const toggle_classes = (el, class1, class2) => {
        classList = el.classList;
        if (classList.contains(class1)) {
            classList.remove(class1)
            classList.add(class2)
        } else {
            classList.remove(class2)
            classList.add(class1)
        }
    }

    const flatten_tree = (node, acc = []) => {
        if (!node || !node.children) return acc;

        for (const childId of node.children) {
            const child = get_node(childId);
            if (!child?.el) continue;

            acc.push(child.el);
            flatten_tree(child, acc);
        }

        return acc;
    };

    const append_tree = (node) => {
        if (!node?.el || !node.children) return;

        const rows = flatten_tree(node);

        let $anchor = node.el;
        for (const $row of rows) {
            $anchor.after($row);
            $anchor = $row;
        }
    };

    const toggle = (id) => {
        const node = get_node(id);
        console.log(node);
        if (node.collapsed) {
            for (const childId of node.children) {
                const child = get_node(childId);
                child.el.removeClass("<?= ZBX_STYLE_DISPLAY_NONE ?>");
            }
            node.collapsed = false;
        } else {
            collapse_all(id);
        }
    };

    const collapse_all = (id) => {
        const node = get_node(id);

        for (const childrenIds of node.children) {
            collapse_all(childrenIds);
            const children = get_node(childrenIds);
            children.collapsed = true;
            children.el.addClass("<?= ZBX_STYLE_DISPLAY_NONE ?>")
        }

        node.collapsed = true;
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'd' || event.key === 'D') {
            console.log(host_tree);
            console.log(host_tree_table);
        }
    });

    const fetch_hostgroups = (id) => {
        return $.ajax({
            url: 'zabbix.php?action=hosttree.data&hostgroup_id=' + id,
            type: 'post',
            dataType: 'json'
        })
    }
</script>