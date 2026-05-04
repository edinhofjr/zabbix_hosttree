type HostTreeNodeType = 'group' | 'pontos' | 'infra' | 'bucket' | 'host';

type HostTreeSeverityMeta = Record<string, {
    name: string;
    class: string;
}>;

type HostTreeNodePayload = {
    id: string;
    label: string;
    level: number;
    type: HostTreeNodeType;
    can_expand: boolean;
    needs_load: boolean;
    popup: boolean;
    problem_host_id: string | null;
    menu_popup: Record<string, unknown> | null;
    problem_counts_by_severity: Record<string, number | string>;
    children: HostTreeNodePayload[];
    description: string | null;
    interface: string | null;
};

type HostTreeViewPayload = {
    status: string;
    nodes: HostTreeNodePayload[];
    selected_group_ids?: string[];
    filter_options?: unknown;
    profile_debug?: unknown;
    severity_meta: HostTreeSeverityMeta;
};

type HostTreeDataPayload = {
    status: string;
    data?: HostTreeNodePayload[];
};

type HostTreeRefreshPayload = {
    status: string;
    data?: HostTreeNodePayload[];
    severity_meta?: HostTreeSeverityMeta;
};

type HostTreeUiConfig = {
    display_none_class: string;
    treeview_class: string;
    arrow_right_class: string;
    arrow_down_class: string;
    nowrap_class: string;
    problem_icon_list_class: string;
    problem_icon_list_item_class: string;
    problem_icon_link_class: string;
    link_action_class: string;
};

type HostTreeBootstrap = {
    payload: HostTreeViewPayload;
    ui: HostTreeUiConfig;
    endpoints: {
        hosttree_data: string;
        hosttree_view_refresh: string;
        hosttree_acknowledge: string;
    };
};

type HostTreeNodeState = {
    data: Omit<HostTreeNodePayload, 'children'>;
    element: HTMLTableRowElement;
    childrenIds: string[];
    loaded: boolean;
    collapsed: boolean;
    parentId: string | null;
    pagination: HostTreeNodePagination | null;
};

type HostTreeNodePagination = {
    page: number;
    pageSize: number;
    total: number;
    totalPages: number;
    prevButton: HTMLButtonElement | null;
    nextButton: HTMLButtonElement | null;
    infoElement: HTMLSpanElement | null;
};

declare class CTabFilter {
    public constructor(element: Element, options: unknown);
}

declare function PopUp(action: string, parameters: Record<string, unknown>, options: Record<string, unknown>): unknown;

(() => {
    const hostTreeWindow = window as Window & {
        hosttreeBootstrap?: HostTreeBootstrap;
    };
    const bootstrap = hostTreeWindow.hosttreeBootstrap;

    if (!bootstrap) {
        return;
    }

    const safeBootstrap: HostTreeBootstrap = bootstrap;
    const payload = bootstrap.payload;
    const ui = bootstrap.ui;

    let severityMeta = payload.severity_meta ?? {};
    let severityOrder = Object.keys(severityMeta).sort((left, right) => Number(right) - Number(left));
    const nodeStateById = new Map<string, HostTreeNodeState>();
    const POINT_BUCKET_PAGE_SIZE = 30;
    const PONTOS_PAGE_SIZE = 20;
    const selectedGroupIds = Array.isArray(payload.selected_group_ids)
        ? payload.selected_group_ids.filter((groupId) => /^\d+$/.test(groupId))
        : [];
    let refreshInProgress = false;

    const table = document.querySelector<HTMLTableElement>('table#host_tree');
    const tableBody = table?.querySelector<HTMLTableSectionElement>('tbody') ?? null;

    if (!table || !tableBody) {
        return;
    }

    const rootTableBody: HTMLTableSectionElement = tableBody;

    initTabFilter(payload.filter_options);
    insertRefreshButton();
    renderInitialNodes(payload.nodes);

    // Required by menupopup.js when clicking "Host" in the configuration section of the context menu.
    (window as Window & { view?: { editHost: (hostid: string) => void } }).view = {
        editHost(hostid: string): void {
            PopUp('popup.host.edit', { hostid }, {
                dialogueid: 'host_edit',
                dialogue_class: 'modal-popup-large',
                prevent_navigation: true,
            });
        },
    };

    table.addEventListener('click', async (event) => {
        const eventTarget = event.target as HTMLElement | null;

        const acknowledgeBucketButton = eventTarget?.closest<HTMLButtonElement>('button[data-hosttree-acknowledge-bucket]');
        if (acknowledgeBucketButton) {
            event.preventDefault();
            const nodeId = acknowledgeBucketButton.getAttribute('node_id');
            if (!nodeId) {
                return;
            }
            const nodeState = nodeStateById.get(nodeId);
            if (!nodeState) {
                return;
            }
            await acknowledgeBucketIncidents(nodeState, acknowledgeBucketButton);
            return;
        }

        const paginationButton = eventTarget?.closest<HTMLButtonElement>('button[data-hosttree-page]');

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

        const toggleButton = eventTarget?.closest('.wrapper-toggle') as HTMLButtonElement | null;

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

        setToggleLoading(toggleButton, true);

        try {
            await ensureChildrenLoaded(nodeState);
        }
        catch (error) {
            console.error('[hosttree] failed to load node', error);
            showError('Erro ao carregar nós. Tente novamente.');
            setToggleLoading(toggleButton, false);
            return;
        }

        setToggleLoading(toggleButton, false);

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

    function insertRefreshButton(): void {
        const tableEl = table!;

        if (!tableEl.parentElement) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.style.marginBottom = '8px';

        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = 'Atualizar incidentes';
        button.setAttribute('data-hosttree-refresh-incidents', '1');

        button.addEventListener('click', async () => {
            if (refreshInProgress) {
                return;
            }

            refreshInProgress = true;
            button.disabled = true;
            button.textContent = 'Atualizando...';

            try {
                await refreshIncidents();
            }
            catch (error) {
                console.error('[hosttree] failed to refresh incidents', error);
                showError('Erro ao atualizar incidentes. Tente novamente.');
            }
            finally {
                refreshInProgress = false;
                button.disabled = false;
                button.textContent = 'Atualizar incidentes';
            }
        });

        wrapper.appendChild(button);
        tableEl.parentElement.insertBefore(wrapper, tableEl);
    }

    function showError(message: string): void {
        const tableEl = table!;
        const existing = document.getElementById('hosttree-error-banner');

        if (existing) {
            existing.remove();
        }

        const banner = document.createElement('div');
        banner.id = 'hosttree-error-banner';
        banner.textContent = message;
        banner.style.cssText = 'color:#a00;background:#fff0f0;border:1px solid #f5c6cb;padding:6px 10px;margin-bottom:8px;border-radius:3px;';

        if (tableEl.parentElement) {
            tableEl.parentElement.insertBefore(banner, tableEl);
        }

        setTimeout(() => banner.remove(), 5000);
    }

    function initTabFilter(filterOptions: unknown): void {
        if (!filterOptions) {
            return;
        }

        const filterElement = document.querySelector('#monitoring_hosttree_filter');

        if (!filterElement) {
            return;
        }

        new CTabFilter(filterElement, filterOptions);
    }

    function renderInitialNodes(nodes: HostTreeNodePayload[]): void {
        rootTableBody.replaceChildren();
        nodeStateById.clear();

        for (const node of nodes) {
            const nodeState = registerNodeTree(node, false);
            rootTableBody.appendChild(nodeState.element);
        }
    }

    function registerNodeTree(
        node: HostTreeNodePayload,
        hidden: boolean,
        parentId: string | null = null
    ): HostTreeNodeState {
        const existingState = nodeStateById.get(node.id);

        if (existingState) {
            return existingState;
        }

        const childIds: string[] = [];

        for (const childNode of node.children) {
            const childState = registerNodeTree(childNode, true, node.id);
            childIds.push(childState.data.id);
        }

        const stateData: Omit<HostTreeNodePayload, 'children'> = {
            id: node.id,
            label: node.label,
            level: node.level,
            type: node.type ?? 'group',
            can_expand: node.can_expand,
            needs_load: node.needs_load,
            popup: node.popup,
            problem_host_id: node.problem_host_id,
            menu_popup: node.menu_popup,
            problem_counts_by_severity: (childIds.length > 0)
                ? aggregateProblemCountersByChildIds(childIds)
                : node.problem_counts_by_severity,
            description: node.description ?? null,
            interface: node.interface ?? null,
        };

        const nodeState: HostTreeNodeState = {
            data: stateData,
            element: buildRowElement(stateData, hidden),
            childrenIds: childIds,
            loaded: node.needs_load ? false : true,
            collapsed: true,
            parentId,
            pagination: null,
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
                infoElement: null,
            };
        }

        if (isPontosNode(nodeState) && childIds.length > PONTOS_PAGE_SIZE) {
            nodeState.pagination = {
                page: 1,
                pageSize: PONTOS_PAGE_SIZE,
                total: childIds.length,
                totalPages: Math.ceil(childIds.length / PONTOS_PAGE_SIZE),
                prevButton: null,
                nextButton: null,
                infoElement: null,
            };
        }

        nodeStateById.set(node.id, nodeState);
        attachPaginationControls(nodeState);

        return nodeState;
    }

    function buildRowElement(node: Omit<HostTreeNodePayload, 'children'>, hidden: boolean): HTMLTableRowElement {
        const row = document.createElement('tr');
        row.setAttribute('node_id', node.id);

        if (hidden) {
            row.classList.add(ui.display_none_class);
        }

        row.appendChild(buildNameColumn(node));
        row.appendChild(buildDescriptionColumn(node));
        row.appendChild(buildInterfaceColumn(node));
        row.appendChild(buildProblemsColumn(node));

        return row;
    }

    function buildDescriptionColumn(node: Omit<HostTreeNodePayload, 'children'>): HTMLTableCellElement {
        const column = document.createElement('td');

        if (node.type === 'host' && node.description) {
            column.textContent = node.description;
        }

        return column;
    }

    function buildInterfaceColumn(node: Omit<HostTreeNodePayload, 'children'>): HTMLTableCellElement {
        const column = document.createElement('td');

        if (node.type === 'host' && node.interface) {
            column.textContent = node.interface;
        }

        return column;
    }

    function buildNameColumn(node: Omit<HostTreeNodePayload, 'children'>): HTMLTableCellElement {
        const column = document.createElement('td');
        column.style.paddingLeft = `${node.level * 24}px`;

        if (node.can_expand) {
            column.appendChild(buildToggleButton(node.id));
        }

        column.appendChild(document.createTextNode('\u00a0'));
        column.appendChild(buildLabelNode(node));

        if (node.type === 'bucket') {
            const ackBtn = buildBucketAcknowledgeButton(node.id);
            if (!hasAnyProblems(node.problem_counts_by_severity)) {
                ackBtn.style.display = 'none';
            }
            column.appendChild(ackBtn);
        }

        return column;
    }

    function buildToggleButton(nodeId: string): HTMLButtonElement {
        const toggleButton = document.createElement('button');
        toggleButton.type = 'button';
        toggleButton.classList.add('wrapper-toggle', ui.treeview_class);
        toggleButton.setAttribute('node_id', nodeId);

        const icon = document.createElement('span');
        icon.classList.add(ui.arrow_right_class);
        toggleButton.appendChild(icon);

        return toggleButton;
    }

    function buildLabelNode(node: Omit<HostTreeNodePayload, 'children'>): HTMLElement {
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

    function hasAnyProblems(problemCounts: Record<string, number | string>): boolean {
        return Object.values(problemCounts).some((v) => Number(v) > 0);
    }

    function syncBucketAcknowledgeButton(nodeState: HostTreeNodeState): void {
        const btn = nodeState.element.querySelector<HTMLButtonElement>('button[data-hosttree-acknowledge-bucket]');

        if (!btn) {
            return;
        }

        btn.style.display = hasAnyProblems(nodeState.data.problem_counts_by_severity) ? '' : 'none';
    }

    function buildBucketAcknowledgeButton(nodeId: string): HTMLButtonElement {
        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('node_id', nodeId);
        button.setAttribute('data-hosttree-acknowledge-bucket', '1');
        button.textContent = '✔';
        button.title = 'Reconhecer incidentes';
        button.style.cssText = 'margin-left:6px;cursor:pointer;font-size:12px;padding:0 3px;vertical-align:middle;';
        return button;
    }

    function isPointBucketNode(nodeState: HostTreeNodeState): boolean {
        return nodeState.data.type === 'bucket' && nodeState.childrenIds.length > 0;
    }

    function isPontosNode(nodeState: HostTreeNodeState): boolean {
        return nodeState.data.type === 'pontos' && nodeState.childrenIds.length > 0;
    }

    function attachPaginationControls(nodeState: HostTreeNodeState): void {
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

    function updatePaginationControls(nodeState: HostTreeNodeState): void {
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

    function changePaginationPage(nodeState: HostTreeNodeState, nextPage: number): void {
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

    function applyPaginationVisibility(nodeState: HostTreeNodeState): void {
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

            if (!isVisible) {
                childState.element.classList.add(ui.display_none_class);
                for (const desc of collectDescendants(childState)) {
                    desc.element.classList.add(ui.display_none_class);
                }
            } else {
                childState.element.classList.remove(ui.display_none_class);
                if (!childState.collapsed) {
                    restoreDescendantVisibility(childState);
                }
            }
        }
    }

    function restoreDescendantVisibility(nodeState: HostTreeNodeState): void {
        const pagination = nodeState.pagination;
        const start = pagination ? (pagination.page - 1) * pagination.pageSize : 0;
        const end = pagination ? start + pagination.pageSize : nodeState.childrenIds.length;

        for (let index = 0; index < nodeState.childrenIds.length; index++) {
            const childId = nodeState.childrenIds[index];
            const childState = nodeStateById.get(childId);

            if (!childState) {
                continue;
            }

            const isVisible = !pagination || (index >= start && index < end);

            if (!isVisible) {
                childState.element.classList.add(ui.display_none_class);
                for (const desc of collectDescendants(childState)) {
                    desc.element.classList.add(ui.display_none_class);
                }
            } else {
                childState.element.classList.remove(ui.display_none_class);
                if (!childState.collapsed) {
                    restoreDescendantVisibility(childState);
                }
            }
        }
    }

    function buildProblemsColumn(node: Omit<HostTreeNodePayload, 'children'>): HTMLTableCellElement {
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

    function createProblemCounterSeed(): Record<string, number> {
        const seed: Record<string, number> = {};

        if (severityOrder.length > 0) {
            for (const severity of severityOrder) {
                seed[severity] = 0;
            }
        }

        return seed;
    }

    function aggregateProblemCountersByChildIds(childIds: string[]): Record<string, number> {
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

    function refreshProblemsColumn(nodeState: HostTreeNodeState): void {
        const nextProblemsColumn = buildProblemsColumn(nodeState.data);
        const currentProblemsColumn = nodeState.element.children.item(3);

        if (currentProblemsColumn) {
            nodeState.element.replaceChild(nextProblemsColumn, currentProblemsColumn);
        } else {
            nodeState.element.appendChild(nextProblemsColumn);
        }

        if (nodeState.data.type === 'bucket') {
            syncBucketAcknowledgeButton(nodeState);
        }
    }

    function refreshLabelText(nodeState: HostTreeNodeState): void {
        const labelEl = nodeState.element.querySelector<HTMLElement>('b, a.' + ui.link_action_class);

        if (labelEl) {
            labelEl.textContent = nodeState.data.label;
        }
    }

    function recalculateNodeProblemCountersFromChildren(nodeState: HostTreeNodeState): void {
        if (nodeState.childrenIds.length === 0) {
            return;
        }

        nodeState.data.problem_counts_by_severity = aggregateProblemCountersByChildIds(nodeState.childrenIds);
        refreshProblemsColumn(nodeState);
    }

    function refreshAncestorProblemCounters(nodeState: HostTreeNodeState): void {
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

    function buildProblemViewUrl(hostId: string): string {
        const params = new URLSearchParams();
        params.set('action', 'problem.view');
        params.append('hostids[0]', hostId);
        params.set('filter_set', '1');

        return `zabbix.php?${params.toString()}`;
    }

    async function refreshIncidents(): Promise<void> {
        const response = await fetchRefreshedTree();
        const nextNodes = Array.isArray(response?.data) ? response.data : [];

        if (response?.severity_meta) {
            severityMeta = response.severity_meta;
            severityOrder = Object.keys(severityMeta).sort((left, right) => Number(right) - Number(left));
        }

        const nextNodeIds = new Set(nextNodes.map((n) => n.id));
        const existingRootIds = new Set(
            [...nodeStateById.values()]
                .filter((s) => s.parentId === null)
                .map((s) => s.data.id)
        );

        const structureChanged =
            nextNodes.some((n) => !existingRootIds.has(n.id)) ||
            [...existingRootIds].some((id) => !nextNodeIds.has(id));

        if (structureChanged) {
            renderInitialNodes(nextNodes);
            return;
        }

        for (const node of nextNodes) {
            const existingState = nodeStateById.get(node.id);

            if (!existingState) {
                continue;
            }

            existingState.data.problem_counts_by_severity = node.problem_counts_by_severity;
            existingState.data.label = node.label;
            refreshLabelText(existingState);
            refreshProblemsColumn(existingState);
        }
    }

    async function acknowledgeBucketIncidents(bucketState: HostTreeNodeState, button: HTMLButtonElement): Promise<void> {
        const hostIds = bucketState.childrenIds
            .map((id) => nodeStateById.get(id)?.data.problem_host_id)
            .filter((id): id is string => !!id);

        if (hostIds.length === 0) {
            return;
        }

        let message: string;

        try {
            message = await showAcknowledgeDialog(bucketState);
        }
        catch {
            return;
        }

        if (button.disabled) {
            return;
        }

        button.disabled = true;
        const originalText = button.textContent ?? '';
        button.textContent = '…';

        try {
            const result = await fetchAcknowledgeBucket(hostIds, message);

            if (result?.status !== 'ok') {
                throw new Error((result as Record<string, string>)?.message ?? 'Unknown error');
            }

            const groupState = findAncestorByType(bucketState, 'group');

            if (groupState) {
                const freshNodes = await fetchChildNodes(groupState.data.id);
                applyFreshNodeData(freshNodes);
                recalculateNodeProblemCountersFromChildren(groupState);
            }
        }
        catch (error) {
            console.error('[hosttree] failed to acknowledge bucket incidents', error);
            showError('Erro ao reconhecer incidentes. Tente novamente.');
        }
        finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    function showAcknowledgeDialog(bucketState: HostTreeNodeState): Promise<string> {
        return new Promise((resolve, reject) => {
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:9999;display:flex;align-items:center;justify-content:center;';

            const dialog = document.createElement('div');
            dialog.style.cssText = 'background:#fff;border:1px solid #ccc;border-radius:4px;padding:20px;min-width:380px;max-width:500px;box-shadow:0 4px 16px rgba(0,0,0,0.2);';

            const title = document.createElement('h3');
            title.textContent = `Reconhecer incidentes — ${bucketState.data.label}`;
            title.style.cssText = 'margin:0 0 12px;font-size:14px;';

            const label = document.createElement('label');
            label.textContent = 'Descrição:';
            label.style.cssText = 'display:block;margin-bottom:4px;font-size:13px;';

            const textarea = document.createElement('textarea');
            textarea.rows = 3;
            textarea.style.cssText = 'width:100%;box-sizing:border-box;font-size:13px;padding:6px;border:1px solid #aaa;border-radius:3px;resize:vertical;';
            textarea.placeholder = 'Opcional';

            const buttons = document.createElement('div');
            buttons.style.cssText = 'margin-top:14px;display:flex;gap:8px;justify-content:flex-end;';

            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.textContent = 'Cancelar';

            const confirmBtn = document.createElement('button');
            confirmBtn.type = 'button';
            confirmBtn.textContent = 'Reconhecer';
            confirmBtn.style.fontWeight = 'bold';

            const close = (confirmed: boolean) => {
                overlay.remove();
                if (confirmed) {
                    resolve(textarea.value.trim());
                } else {
                    reject(new Error('cancelled'));
                }
            };

            cancelBtn.addEventListener('click', () => close(false));
            confirmBtn.addEventListener('click', () => close(true));
            overlay.addEventListener('click', (e) => { if (e.target === overlay) close(false); });

            buttons.appendChild(cancelBtn);
            buttons.appendChild(confirmBtn);
            dialog.appendChild(title);
            dialog.appendChild(label);
            dialog.appendChild(textarea);
            dialog.appendChild(buttons);
            overlay.appendChild(dialog);
            document.body.appendChild(overlay);
            textarea.focus();
        });
    }

    function fetchAcknowledgeBucket(hostIds: string[], message: string): Promise<Record<string, unknown>> {
        return new Promise((resolve, reject) => {
            const data: Record<string, unknown> = { 'host_ids[]': hostIds };

            if (message) {
                data['message'] = message;
            }

            $.ajax({
                url: safeBootstrap.endpoints.hosttree_acknowledge,
                type: 'post',
                dataType: 'json',
                data,
            })
                .done((response: Record<string, unknown>) => {
                    resolve(response);
                })
                .fail((_jqXHR, textStatus, errorThrown) => {
                    reject(new Error(String(errorThrown ?? textStatus)));
                });
        });
    }

    function findAncestorByType(nodeState: HostTreeNodeState, type: HostTreeNodeType): HostTreeNodeState | null {
        let parentId = nodeState.parentId;

        while (parentId) {
            const parentState = nodeStateById.get(parentId);

            if (!parentState) {
                break;
            }

            if (parentState.data.type === type) {
                return parentState;
            }

            parentId = parentState.parentId;
        }

        return null;
    }

    function applyFreshNodeData(freshNodes: HostTreeNodePayload[]): void {
        for (const node of freshNodes) {
            const existingState = nodeStateById.get(node.id);

            if (existingState) {
                existingState.data.problem_counts_by_severity = node.problem_counts_by_severity;
                existingState.data.label = node.label;
                refreshLabelText(existingState);
                refreshProblemsColumn(existingState);
            }

            if (node.children.length > 0) {
                applyFreshNodeData(node.children);
            }
        }
    }

    async function ensureChildrenLoaded(nodeState: HostTreeNodeState): Promise<void> {
        if (nodeState.loaded || !nodeState.data.needs_load) {
            return;
        }

        const fetchedNodes = await fetchChildNodes(nodeState.data.id);
        const childIds: string[] = [];

        for (const childNode of fetchedNodes) {
            const childState = registerNodeTree(childNode, true, nodeState.data.id);
            childIds.push(childState.data.id);
        }

        nodeState.childrenIds = childIds;
        nodeState.loaded = true;

        recalculateNodeProblemCountersFromChildren(nodeState);
        refreshAncestorProblemCounters(nodeState);
    }

    function fetchRefreshedTree(): Promise<HostTreeRefreshPayload> {
        return new Promise((resolve, reject) => {
            const body: Record<string, string[]> = {};

            if (selectedGroupIds.length > 0) {
                body['groupids[]'] = selectedGroupIds;
            }

            $.ajax({
                url: safeBootstrap.endpoints.hosttree_view_refresh,
                type: 'post',
                dataType: 'json',
                data: body,
            })
                .done((response: HostTreeRefreshPayload) => {
                    resolve(response);
                })
                .fail((_jqXHR, textStatus, errorThrown) => {
                    reject(new Error(String(errorThrown ?? textStatus)));
                });
        });
    }

    function fetchChildNodes(nodeId: string): Promise<HostTreeNodePayload[]> {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: safeBootstrap.endpoints.hosttree_data,
                type: 'post',
                dataType: 'json',
                data: { hostgroup_id: nodeId },
            })
                .done((response: HostTreeDataPayload) => {
                    resolve(Array.isArray(response?.data) ? response.data : []);
                })
                .fail((_jqXHR, textStatus, errorThrown) => {
                    reject(new Error(String(errorThrown ?? textStatus)));
                });
        });
    }

    function expandNode(nodeState: HostTreeNodeState): void {
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

    function collapseNode(nodeState: HostTreeNodeState): void {
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

    function appendDescendants(nodeState: HostTreeNodeState): void {
        const descendants = collectDescendants(nodeState);
        let anchor = nodeState.element;

        for (const descendant of descendants) {
            if (!descendant.element.parentElement) {
                anchor.insertAdjacentElement('afterend', descendant.element);
            }

            anchor = descendant.element;
        }
    }

    function collectDescendants(nodeState: HostTreeNodeState, acc: HostTreeNodeState[] = []): HostTreeNodeState[] {
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

    function getRowToggleButton(row: HTMLTableRowElement): HTMLButtonElement | null {
        return row.querySelector<HTMLButtonElement>('.wrapper-toggle');
    }

    function setToggleExpanded(toggleButton: HTMLButtonElement | null, expanded: boolean): void {
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

    function setToggleLoading(toggleButton: HTMLButtonElement, loading: boolean): void {
        toggleButton.disabled = loading;

        const icon = toggleButton.querySelector('span');

        if (!icon) {
            return;
        }

        if (loading) {
            icon.classList.remove(ui.arrow_right_class, ui.arrow_down_class);
            icon.style.opacity = '0.4';
        }
        else {
            icon.style.opacity = '';
            icon.classList.add(ui.arrow_right_class);
        }
    }

    function disableToggleButton(toggleButton: HTMLButtonElement): void {
        toggleButton.setAttribute('disabled', 'disabled');

        const icon = toggleButton.querySelector('span');

        if (!icon) {
            return;
        }

        icon.classList.remove(ui.arrow_down_class, ui.arrow_right_class);
    }
})();
