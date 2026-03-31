# Host Tree v2 — Zabbix Module

A Zabbix module that provides an interactive, hierarchical tree view of monitored hosts and their problem statuses. Organizes hosts by host groups and custom tags, giving a clear visual overview of your infrastructure health.

---

## Features

- **Hierarchical tree view** — expandable/collapsible host groups with lazy loading
- **Problem severity display** — color-coded problem counts per node (Disaster, High, Average, Warning, Info)
- **Custom tag grouping** — automatically organizes hosts by `ponto` tag into location buckets
- **Filter management** — multiselect group filter with persistence across sessions
- **Problem acknowledgement** — bulk acknowledge problems for selected hosts with optional message
- **Auto-refresh** — updates problem counts without full page reload
- **Performance optimized** — 5-minute host group cache, single API calls for counts, pagination for large groups

---

## How It Works

### Architecture Overview

```
Monitoring → Host Tree (menu entry)
        │
        ▼
HostTreeController          ← Initial page load
        │
        ├── HostGroupCache  ← 5-min TTL cache (/tmp/zbx_hosttree_hostgroups.json)
        ├── HostTreeAPIService ← Wraps Zabbix PHP API
        └── hosttree.view.php ← Renders HTML + JS bootstrap
                │
                ▼
        hosttree.view.action.js  ← Interactive tree logic (compiled from TypeScript)
                │
                ├── [expand click] → HostTreeDataController (AJAX)
                ├── [refresh]      → HostTreeViewRefreshController (AJAX)
                └── [acknowledge]  → HostTreeAcknowledgeController (AJAX)
```

### Tree Structure

The tree is organized in up to 4 levels:

| Level | Content |
|-------|---------|
| **0** | Root host groups (filtered by user selection) |
| **1** | **Pontos** (hosts with `ponto` tag, grouped by value) + **Infra** (untagged hosts) |
| **2** | Subgroups within a Ponto bucket |
| **3** | Individual hosts with problem icons |

### Data Flow

1. User navigates to **Monitoring → Host Tree**
2. `HostTreeController` loads host groups (from cache or API), reads user's saved filter preferences and fetches problem counts for the selected groups
3. The view renders the initial table with root-level group nodes and loads the JavaScript
4. The browser initializes `hosttree.view.action.js`, which sets up event listeners and renders problem icons
5. **Expand** → AJAX call to `HostTreeDataController` returns child nodes with problem counts
6. **Filter change** → page reloads with updated group selection (saved in user profile)
7. **Acknowledge** → AJAX call to `HostTreeAcknowledgeController` acknowledges events via Zabbix API

---

## Project Structure

```
hosttree_v2/
├── Module.php                          # Module bootstrap & menu registration
├── manifest.json                       # Module metadata & action routing
├── package.json                        # Node.js dev dependencies (TypeScript)
├── tsconfig.json                       # TypeScript compiler config
│
├── actions/
│   ├── HostTreeController.php          # Main page controller
│   ├── HostTreeDataController.php      # AJAX: expand tree node
│   ├── HostTreeViewRefreshController.php # AJAX: refresh root nodes
│   └── HostTreeAcknowledgeController.php # AJAX: acknowledge problems
│
├── services/
│   ├── HostTreeAPIService.php          # Zabbix API wrapper & tree logic
│   └── HostGroupCache.php              # File-based cache (5-min TTL)
│
├── classes/
│   ├── HostTreeNodeFactory.php         # Factory for tree node arrays
│   ├── HostTreeTable.php               # HTML table component
│   ├── HostTreeTableRow.php            # HTML table row component
│   ├── HTMLHelper.php                  # HTML rendering utilities
│   ├── CProfileExample.php             # User preference persistence
│   ├── dto/                            # Data Transfer Objects
│   └── interfaces/                     # Interface definitions
│
├── partials/
│   ├── module.monitoring.hosttree.view.html.php  # Main content partial
│   ├── module.monitoring.hosttree.filter.php     # Filter panel partial
│   ├── js/hosttree.view.js.php                   # JS bootstrap config
│   ├── js/hosttree.view.action.js                # Compiled JS (do not edit)
│   └── ts/hosttree.view.action.ts                # TypeScript source
│
├── views/
│   └── hosttree.view.php               # Main view template
│
└── docs/
    └── fluxo-completo-modulo-hosttree.svg  # Architecture flow diagram
```

---

## Requirements

- **Zabbix** 5.0 or higher
- **PHP** 7.4 or higher
- **Node.js** + **npm** (for TypeScript compilation only)

---

## Installation

1. Copy the module directory to your Zabbix frontend modules folder:

   ```bash
   cp -r hosttree_v2 /usr/share/zabbix/modules/
   ```

2. Set the correct permissions:

   ```bash
   chown -R www-data:www-data /usr/share/zabbix/modules/hosttree_v2
   ```

3. In the Zabbix web interface, go to **Administration → General → Modules** and click **Scan directory**

4. Find **Host Tree v2** in the list and enable it

5. Navigate to **Monitoring → Host Tree** to use the module

---

## Development

### Compiling TypeScript

```bash
cd /usr/share/zabbix/modules/hosttree_v2
npm install
npx tsc
```

The compiled output goes to `partials/js/hosttree.view.action.js`. Do not edit this file directly — edit the TypeScript source at `partials/ts/hosttree.view.action.ts` instead.

### Tag-based Grouping (`ponto`)

Hosts are automatically grouped by the value of a tag named `ponto`. For example, hosts with `ponto=Centro` will appear under a "Centro" bucket inside their parent host group. Hosts without this tag are grouped under an **Infra** node.

To use a different tag name, update the `getPontoBucket()` method in `services/HostTreeAPIService.php`.

---

## Acknowledgement

To acknowledge problems for a group of hosts, expand the tree to the host level, select the hosts and click the **Acknowledge** button. An optional message can be attached to the acknowledgement. The number of acknowledged events is returned as feedback.

---

## Author

**edinhofjr** — v1.0.0