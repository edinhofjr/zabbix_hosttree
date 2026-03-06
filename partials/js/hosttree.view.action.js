"use strict";
(() => {
    const hostTreeWindow = window;
    const bootstrap = hostTreeWindow.hosttreeBootstrap;
    if (!bootstrap) {
        return;
    }
    const safeBootstrap = bootstrap;
    const payload = bootstrap.payload;
    const ui = bootstrap.ui;
    if (payload.profile_debug !== undefined) {
        console.debug('[hosttree] profile_debug', payload.profile_debug);
    }
    const severityMeta = payload.severity_meta ?? {};
    const severityOrder = Object.keys(severityMeta).sort((left, right) => Number(right) - Number(left));
    const nodeStateById = new Map();
    const POINT_BUCKET_PAGE_SIZE = 30;
    const table = document.querySelector('table#host_tree');
    const tableBody = table?.querySelector('tbody') ?? null;
    if (!table || !tableBody) {
        return;
    }
    const rootTableBody = tableBody;
    initTabFilter(payload.filter_options);
    renderInitialNodes(payload.nodes);
    table.addEventListener('click', async (event) => {
        const eventTarget = event.target;
        const paginationButton = eventTarget?.closest('button[data-hosttree-page]');
        if (paginationButton) {
            event.preventDefault();
            const nodeId = paginationButton.getAttribute('node_id');
            const direction = paginationButton.getAttribute('data-hosttree-page');
            if (!nodeId || !direction) {
                return;
            }
            const nodeState = nodeStateById.get(nodeId);
            if (!nodeState || !nodeState.pagination || nodeState.collapsed) {
                return;
            }
            if (direction === 'prev') {
                changePaginationPage(nodeState, nodeState.pagination.page - 1);
            }
            else if (direction === 'next') {
                changePaginationPage(nodeState, nodeState.pagination.page + 1);
            }
            return;
        }
        const toggleButton = eventTarget?.closest('.wrapper-toggle');
        if (!toggleButton) {
            return;
        }
        event.preventDefault();
        const nodeId = toggleButton.getAttribute('node_id');
        if (!nodeId) {
            return;
        }
        const nodeState = nodeStateById.get(nodeId);
        if (!nodeState || !nodeState.data.can_expand) {
            return;
        }
        try {
            await ensureChildrenLoaded(nodeState);
        }
        catch (error) {
            console.error('[hosttree] failed to load node', error);
            return;
        }
        if (nodeState.childrenIds.length === 0) {
            disableToggleButton(toggleButton);
            return;
        }
        if (nodeState.collapsed) {
            expandNode(nodeState);
            setToggleExpanded(toggleButton, true);
            return;
        }
        collapseNode(nodeState);
        setToggleExpanded(toggleButton, false);
    });
    function initTabFilter(filterOptions) {
        if (!filterOptions) {
            return;
        }
        const filterElement = document.querySelector('#monitoring_hosttree_filter');
        if (!filterElement) {
            return;
        }
        new CTabFilter(filterElement, filterOptions);
    }
    function renderInitialNodes(nodes) {
        rootTableBody.replaceChildren();
        nodeStateById.clear();
        for (const node of nodes) {
            const nodeState = registerNodeTree(node, false);
            rootTableBody.appendChild(nodeState.element);
        }
    }
    function registerNodeTree(node, hidden, parentId = null, inPointsSubtree = false) {
        const existingState = nodeStateById.get(node.id);
        if (existingState) {
            return existingState;
        }
        const isPointsRoot = (node.level === 1 && /^Pontos\b/i.test(node.label));
        const isInPointsSubtree = inPointsSubtree || isPointsRoot;
        const childIds = [];
        for (const childNode of node.children) {
            const childState = registerNodeTree(childNode, true, node.id, isInPointsSubtree);
            childIds.push(childState.data.id);
        }
        const stateData = {
            id: node.id,
            label: node.label,
            level: node.level,
            can_expand: node.can_expand,
            needs_load: node.needs_load,
            popup: node.popup,
            problem_host_id: node.problem_host_id,
            menu_popup: node.menu_popup,
            problem_counts_by_severity: (childIds.length > 0)
                ? aggregateProblemCountersByChildIds(childIds)
                : node.problem_counts_by_severity
        };
        const nodeState = {
            data: stateData,
            element: buildRowElement(stateData, hidden),
            childrenIds: childIds,
            loaded: node.needs_load ? false : true,
            collapsed: true,
            parentId,
            inPointsSubtree: isInPointsSubtree,
            pagination: null
        };
        if (childIds.length > 0) {
            nodeState.loaded = true;
        }
        if (isPointBucketNode(nodeState) && childIds.length > POINT_BUCKET_PAGE_SIZE) {
            nodeState.pagination = {
                page: 1,
                pageSize: POINT_BUCKET_PAGE_SIZE,
                total: childIds.length,
                totalPages: Math.ceil(childIds.length / POINT_BUCKET_PAGE_SIZE),
                prevButton: null,
                nextButton: null,
                infoElement: null
            };
        }
        nodeStateById.set(node.id, nodeState);
        attachPaginationControls(nodeState);
        return nodeState;
    }
    function buildRowElement(node, hidden) {
        const row = document.createElement('tr');
        row.setAttribute('node_id', node.id);
        if (hidden) {
            row.classList.add(ui.display_none_class);
        }
        row.appendChild(buildNameColumn(node));
        row.appendChild(buildProblemsColumn(node));
        return row;
    }
    function buildNameColumn(node) {
        const column = document.createElement('td');
        column.appendChild(document.createTextNode('\u00a0'.repeat(Math.max(0, node.level * 6))));
        if (node.can_expand) {
            column.appendChild(buildToggleButton(node.id));
        }
        column.appendChild(document.createTextNode('\u00a0'));
        column.appendChild(buildLabelNode(node));
        return column;
    }
    function buildToggleButton(nodeId) {
        const toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.classList.add('wrapper-toggle', ui.treeview_class);
        toggleButton.setAttribute('node_id', nodeId);
        const icon = document.createElement('span');
        icon.classList.add(ui.arrow_right_class);
        toggleButton.appendChild(icon);
        return toggleButton;
    }
    function buildLabelNode(node) {
        if (node.popup && node.menu_popup) {
            const link = document.createElement('a');
            link.textContent = node.label;
            link.href = 'javascript:void(0)';
            link.classList.add(ui.link_action_class);
            link.setAttribute('role', 'button');
            link.setAttribute('aria-expanded', 'false');
            link.setAttribute('aria-haspopup', 'true');
            link.setAttribute('data-menu-popup', JSON.stringify(node.menu_popup));
            return link;
        }
        const text = document.createElement('b');
        text.textContent = node.label;
        return text;
    }
    function isPointBucketNode(nodeState) {
        return nodeState.inPointsSubtree
            && nodeState.data.level === 2
            && nodeState.childrenIds.length > 0;
    }
    function attachPaginationControls(nodeState) {
        if (!nodeState.pagination) {
            return;
        }
        const firstCell = nodeState.element.querySelector('td');
        if (!firstCell) {
            return;
        }
        const controls = document.createElement('span');
        controls.classList.add(ui.nowrap_class);
        controls.style.marginLeft = '10px';
        controls.style.display = 'inline-flex';
        controls.style.alignItems = 'center';
        controls.style.gap = '4px';
        const prevButton = document.createElement('button');
        prevButton.type = 'button';
        prevButton.setAttribute('node_id', nodeState.data.id);
        prevButton.setAttribute('data-hosttree-page', 'prev');
        prevButton.textContent = '<';
        prevButton.disabled = true;
        const nextButton = document.createElement('button');
        nextButton.type = 'button';
        nextButton.setAttribute('node_id', nodeState.data.id);
        nextButton.setAttribute('data-hosttree-page', 'next');
        nextButton.textContent = '>';
        const info = document.createElement('span');
        info.style.minWidth = '42px';
        info.style.textAlign = 'center';
        controls.appendChild(prevButton);
        controls.appendChild(info);
        controls.appendChild(nextButton);
        firstCell.appendChild(controls);
        nodeState.pagination.prevButton = prevButton;
        nodeState.pagination.nextButton = nextButton;
        nodeState.pagination.infoElement = info;
        updatePaginationControls(nodeState);
    }
    function updatePaginationControls(nodeState) {
        const pagination = nodeState.pagination;
        if (!pagination) {
            return;
        }
        if (pagination.infoElement) {
            pagination.infoElement.textContent = `${pagination.page}/${pagination.totalPages}`;
        }
        if (pagination.prevButton) {
            pagination.prevButton.disabled = pagination.page <= 1;
        }
        if (pagination.nextButton) {
            pagination.nextButton.disabled = pagination.page >= pagination.totalPages;
        }
    }
    function changePaginationPage(nodeState, nextPage) {
        const pagination = nodeState.pagination;
        if (!pagination) {
            return;
        }
        const clampedPage = Math.max(1, Math.min(pagination.totalPages, nextPage));
        if (clampedPage === pagination.page) {
            return;
        }
        pagination.page = clampedPage;
        applyPaginationVisibility(nodeState);
        updatePaginationControls(nodeState);
    }
    function applyPaginationVisibility(nodeState) {
        const pagination = nodeState.pagination;
        if (!pagination) {
            return;
        }
        const start = (pagination.page - 1) * pagination.pageSize;
        const end = start + pagination.pageSize;
        for (let index = 0; index < nodeState.childrenIds.length; index++) {
            const childId = nodeState.childrenIds[index];
            const childState = nodeStateById.get(childId);
            if (!childState) {
                continue;
            }
            const isVisible = (index >= start && index < end);
            childState.element.classList.toggle(ui.display_none_class, !isVisible);
        }
    }
    function buildProblemsColumn(node) {
        const problemsColumn = document.createElement('td');
        problemsColumn.classList.add(ui.nowrap_class);
        const severityKeys = severityOrder.length > 0
            ? severityOrder
            : Object.keys(node.problem_counts_by_severity).sort((left, right) => Number(right) - Number(left));
        const problemList = document.createElement('div');
        problemList.classList.add(ui.problem_icon_list_class);
        let hasProblems = false;
        for (const severity of severityKeys) {
            const problemCount = Number(node.problem_counts_by_severity[severity] ?? 0);
            if (!Number.isFinite(problemCount) || problemCount <= 0) {
                continue;
            }
            hasProblems = true;
            const severityBadge = document.createElement('span');
            severityBadge.textContent = String(problemCount);
            severityBadge.classList.add(ui.problem_icon_list_item_class);
            if (severityMeta[severity]?.class) {
                severityBadge.classList.add(severityMeta[severity].class);
            }
            if (severityMeta[severity]?.name) {
                severityBadge.title = severityMeta[severity].name;
            }
            problemList.appendChild(severityBadge);
        }
        if (!hasProblems) {
            return problemsColumn;
        }
        if (node.problem_host_id) {
            const link = document.createElement('a');
            link.classList.add(ui.problem_icon_link_class);
            link.href = buildProblemViewUrl(node.problem_host_id);
            link.appendChild(problemList);
            problemsColumn.appendChild(link);
            return problemsColumn;
        }
        problemsColumn.appendChild(problemList);
        return problemsColumn;
    }
    function createProblemCounterSeed() {
        const seed = {};
        if (severityOrder.length > 0) {
            for (const severity of severityOrder) {
                seed[severity] = 0;
            }
        }
        return seed;
    }
    function aggregateProblemCountersByChildIds(childIds) {
        const counters = createProblemCounterSeed();
        for (const childId of childIds) {
            const childState = nodeStateById.get(childId);
            if (!childState) {
                continue;
            }
            for (const [severity, rawProblemCount] of Object.entries(childState.data.problem_counts_by_severity)) {
                const problemCount = Number(rawProblemCount);
                if (!Number.isFinite(problemCount) || problemCount <= 0) {
                    if (!Object.prototype.hasOwnProperty.call(counters, severity)) {
                        counters[severity] = 0;
                    }
                    continue;
                }
                counters[severity] = (counters[severity] ?? 0) + problemCount;
            }
        }
        return counters;
    }
    function refreshProblemsColumn(nodeState) {
        const nextProblemsColumn = buildProblemsColumn(nodeState.data);
        const currentProblemsColumn = nodeState.element.children.item(1);
        if (currentProblemsColumn) {
            nodeState.element.replaceChild(nextProblemsColumn, currentProblemsColumn);
            return;
        }
        nodeState.element.appendChild(nextProblemsColumn);
    }
    function recalculateNodeProblemCountersFromChildren(nodeState) {
        if (nodeState.childrenIds.length === 0) {
            return;
        }
        nodeState.data.problem_counts_by_severity = aggregateProblemCountersByChildIds(nodeState.childrenIds);
        refreshProblemsColumn(nodeState);
    }
    function refreshAncestorProblemCounters(nodeState) {
        let ancestorId = nodeState.parentId;
        while (ancestorId) {
            const ancestorState = nodeStateById.get(ancestorId);
            if (!ancestorState) {
                break;
            }
            recalculateNodeProblemCountersFromChildren(ancestorState);
            ancestorId = ancestorState.parentId;
        }
    }
    function buildProblemViewUrl(hostId) {
        const params = new URLSearchParams();
        params.set('action', 'problem.view');
        params.append('hostids[0]', hostId);
        params.set('filter_set', '1');
        return `zabbix.php?${params.toString()}`;
    }
    async function ensureChildrenLoaded(nodeState) {
        if (nodeState.loaded || !nodeState.data.needs_load) {
            return;
        }
        const fetchedNodes = await fetchChildNodes(nodeState.data.id);
        const childIds = [];
        for (const childNode of fetchedNodes) {
            const childState = registerNodeTree(childNode, true);
            childIds.push(childState.data.id);
        }
        nodeState.childrenIds = childIds;
        nodeState.loaded = true;
        recalculateNodeProblemCountersFromChildren(nodeState);
        refreshAncestorProblemCounters(nodeState);
    }
    function fetchChildNodes(nodeId) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: `${safeBootstrap.endpoints.hosttree_data}&hostgroup_id=${encodeURIComponent(nodeId)}`,
                type: 'post',
                dataType: 'json'
            })
                .done((response) => {
                resolve(Array.isArray(response?.data) ? response.data : []);
            })
                .fail((_jqXHR, textStatus, errorThrown) => {
                reject(new Error(String(errorThrown ?? textStatus)));
            });
        });
    }
    function expandNode(nodeState) {
        appendDescendants(nodeState);
        for (const childId of nodeState.childrenIds) {
            const childState = nodeStateById.get(childId);
            if (!childState) {
                continue;
            }
            childState.element.classList.remove(ui.display_none_class);
        }
        applyPaginationVisibility(nodeState);
        updatePaginationControls(nodeState);
        nodeState.collapsed = false;
    }
    function collapseNode(nodeState) {
        for (const childId of nodeState.childrenIds) {
            const childState = nodeStateById.get(childId);
            if (!childState) {
                continue;
            }
            collapseNode(childState);
            childState.element.classList.add(ui.display_none_class);
            childState.collapsed = true;
            setToggleExpanded(getRowToggleButton(childState.element), false);
        }
        nodeState.collapsed = true;
    }
    function appendDescendants(nodeState) {
        const descendants = collectDescendants(nodeState);
        let anchor = nodeState.element;
        for (const descendant of descendants) {
            anchor.insertAdjacentElement('afterend', descendant.element);
            anchor = descendant.element;
        }
    }
    function collectDescendants(nodeState, acc = []) {
        for (const childId of nodeState.childrenIds) {
            const childState = nodeStateById.get(childId);
            if (!childState) {
                continue;
            }
            acc.push(childState);
            collectDescendants(childState, acc);
        }
        return acc;
    }
    function getRowToggleButton(row) {
        return row.querySelector('.wrapper-toggle');
    }
    function setToggleExpanded(toggleButton, expanded) {
        if (!toggleButton) {
            return;
        }
        const icon = toggleButton.querySelector('span');
        if (!icon) {
            return;
        }
        icon.classList.toggle(ui.arrow_down_class, expanded);
        icon.classList.toggle(ui.arrow_right_class, !expanded);
    }
    function disableToggleButton(toggleButton) {
        toggleButton.setAttribute('disabled', 'disabled');
        const icon = toggleButton.querySelector('span');
        if (!icon) {
            return;
        }
        icon.classList.remove(ui.arrow_down_class, ui.arrow_right_class);
    }
})();
