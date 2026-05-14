document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('sqlManagerApp');
    const bootstrapNode = document.getElementById('sqlmBootstrap');

    if (!app || !bootstrapNode) {
        return;
    }

    const bootstrapData = JSON.parse(bootstrapNode.textContent || '{}');
    const state = {
        csrfToken: bootstrapData.csrf_token || '',
        tables: Array.isArray(bootstrapData.tables) ? bootstrapData.tables : [],
        context: null,
        activeTab: 'data',
        selectedTable: bootstrapData.selected_table || '',
        selectedRowIndex: null,
        modalMode: 'update',
        queryResult: null,
        wrapText: false,
        databaseExpanded: true,
        explorerCollapsed: window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches,
        expandedTables: {},
        structureFocus: 'columns',
        inlineEdit: null,
        cellStatus: {},
        columnWidths: {},
        resize: null,
        explorerWidth: Number(window.localStorage.getItem('sqlm:explorerWidth') || 286),
        cellPreview: {
            rowIndex: null,
            column: '',
            value: '',
        },
        chartInstance: null,
        transaction: {
            active: false,
            table: '',
            snapshot: null,
            queue: [],
            busy: false,
            localCounter: 0,
        },
        queryError: null,
        importReport: null,
        healthSummary: bootstrapData.module_health && typeof bootstrapData.module_health === 'object'
            ? bootstrapData.module_health
            : { modules: {}, unhealthy_modules: [] },
    };

    const refs = {
        flash: document.getElementById('sqlmFlash'),
        databaseToggle: document.getElementById('sqlmDatabaseToggle'),
        databaseStatus: document.getElementById('sqlmDatabaseStatus'),
        explorerToggleBtn: document.getElementById('sqlmExplorerToggleBtn'),
        explorerSplitter: document.getElementById('sqlmExplorerSplitter'),
        explorerState: document.getElementById('sqlmExplorerState'),
        currentTable: document.getElementById('sqlmCurrentTable'),
        headTable: document.getElementById('sqlmHeadTable'),
        tableCountPill: document.getElementById('sqlmTableCountPill'),
        modeTitle: document.getElementById('sqlmModeTitle'),
        modeDescription: document.getElementById('sqlmModeDescription'),
        modeBadge: document.getElementById('sqlmModeBadge'),
        tableSearch: document.getElementById('sqlmTableSearch'),
        tableList: document.getElementById('sqlmTableList'),
        primaryKeys: document.getElementById('sqlmPrimaryKeys'),
        rowCount: document.getElementById('sqlmRowCount'),
        engine: document.getElementById('sqlmEngine'),
        txnState: document.getElementById('sqlmTxnState'),
        filterRow: document.getElementById('sqlmFilterRow'),
        searchInput: document.getElementById('sqlmSearchInput'),
        perPage: document.getElementById('sqlmPerPage'),
        sortColumn: document.getElementById('sqlmSortColumn'),
        sortDirection: document.getElementById('sqlmSortDirection'),
        dataPanel: document.getElementById('sqlmDataPanel'),
        structurePanel: document.getElementById('sqlmStructurePanel'),
        queryPanel: document.getElementById('sqlmQueryPanel'),
        searchPanel: document.getElementById('sqlmSearchPanel'),
        insertPanel: document.getElementById('sqlmInsertPanel'),
        exportPanel: document.getElementById('sqlmExportPanel'),
        importPanel: document.getElementById('sqlmImportPanel'),
        operationsPanel: document.getElementById('sqlmOperationsPanel'),
        queryInput: document.getElementById('sqlmQueryInput'),
        reloadBtn: document.getElementById('sqlmReloadBtn'),
        insertBtn: document.getElementById('sqlmInsertBtn'),
        editBtn: document.getElementById('sqlmEditBtn'),
        deleteBtn: document.getElementById('sqlmDeleteBtn'),
        applyBtn: document.getElementById('sqlmApplyBtn'),
        runQueryBtn: document.getElementById('sqlmRunQueryBtn'),
        clearQueryBtn: document.getElementById('sqlmClearQueryBtn'),
        rowModalTitle: document.getElementById('sqlmRowModalTitle'),
        rowModalSubtitle: document.getElementById('sqlmRowModalSubtitle'),
        rowForm: document.getElementById('sqlmRowForm'),
        saveRowBtn: document.getElementById('sqlmSaveRowBtn'),
        txnBtn: document.getElementById('sqlmTxnBtn'),
        commitBtn: document.getElementById('sqlmCommitBtn'),
        rollbackBtn: document.getElementById('sqlmRollbackBtn'),
        wrapBtn: document.getElementById('sqlmWrapBtn'),
        focusFilterBtn: document.getElementById('sqlmFocusFilterBtn'),
        quickSortBtn: document.getElementById('sqlmQuickSortBtn'),
        importBtn: document.getElementById('sqlmImportBtn'),
        exportBtn: document.getElementById('sqlmExportBtn'),
        generateBtn: document.getElementById('sqlmGenerateBtn'),
        chartBtn: document.getElementById('sqlmChartBtn'),
        importInput: document.getElementById('sqlmImportInput'),
        chartType: document.getElementById('sqlmChartType'),
        chartLabelColumn: document.getElementById('sqlmChartLabelColumn'),
        chartValueColumn: document.getElementById('sqlmChartValueColumn'),
        renderChartBtn: document.getElementById('sqlmRenderChartBtn'),
        chartCanvas: document.getElementById('sqlmChartCanvas'),
        chartEmpty: document.getElementById('sqlmChartEmpty'),
        statusLabel: document.getElementById('sqlmStatusLabel'),
        statusSummary: document.getElementById('sqlmStatusSummary'),
        statusSelection: document.getElementById('sqlmStatusSelection'),
        prevPageBtn: document.getElementById('sqlmPrevPageBtn'),
        nextPageBtn: document.getElementById('sqlmNextPageBtn'),
    };
    const endpoints = {
        tableData: app.getAttribute('data-table-url') || '',
        query: app.getAttribute('data-query-url') || '',
        commit: app.getAttribute('data-commit-url') || '',
        import: app.getAttribute('data-import-url') || '',
        exportSql: app.getAttribute('data-export-sql-url') || '',
        insert: app.getAttribute('data-insert-url') || '',
        update: app.getAttribute('data-update-url') || '',
        delete: app.getAttribute('data-delete-url') || '',
    };
    const rowModalElement = document.getElementById('sqlmRowModal');
    const chartModalElement = document.getElementById('sqlmChartModal');
    let rowModal = window.bootstrap && rowModalElement ? new bootstrap.Modal(rowModalElement) : null;
    let chartModal = window.bootstrap && chartModalElement ? new bootstrap.Modal(chartModalElement) : null;

    /* HELPERS */

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function selectorEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return String(value).replace(/"/g, '\\"');
    }

    function deepClone(value) {
        if (typeof window.structuredClone === 'function') {
            return window.structuredClone(value);
        }

        return JSON.parse(JSON.stringify(value));
    }

    function showFlash(message, type = 'success') {
        refs.flash.innerHTML = message
            ? `<div class="alert alert-${escapeHtml(type)} alert-dismissible fade show" role="alert">${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`
            : '';
    }

    function hasActiveTransaction() {
        return state.transaction.active;
    }

    function transactionPendingCount() {
        return state.transaction.queue.length;
    }

    function cellHtml(value) {
        if (value === null || value === undefined) {
            return '<span class="sqlm-null">NULL</span>';
        }

        const text = String(value);
        return text === '' ? '<span class="text-secondary">(rỗng)</span>' : escapeHtml(text);
    }

    function decorateContext(context) {
        if (!context || !Array.isArray(context.rows)) {
            return context;
        }

        const identityKeys = rowIdentityKeys(context);
        context.rows = context.rows.map((row) => {
            const item = row && typeof row === 'object' ? row : {};
            if (!item.__sqlmRowKey && identityKeys.length > 0) {
                item.__sqlmRowKey = {};
                identityKeys.forEach((key) => {
                    item.__sqlmRowKey[key] = item[key];
                });
            }
            if (item.__sqlmPending !== true) {
                item.__sqlmPending = false;
            }
            return item;
        });

        return context;
    }

    function selectedRow() {
        if (!state.context || state.selectedRowIndex === null || !Array.isArray(state.context.rows)) {
            return null;
        }

        return state.context.rows[state.selectedRowIndex] || null;
    }

    function selectedRowKey() {
        const row = selectedRow();
        if (!row) {
            return null;
        }

        if (row.__sqlmRowKey && typeof row.__sqlmRowKey === 'object') {
            return row.__sqlmRowKey;
        }

        const identityKeys = rowIdentityKeys(state.context);
        if (identityKeys.length === 0) {
            return null;
        }

        const rowKey = {};
        identityKeys.forEach((key) => {
            rowKey[key] = row[key];
        });
        return rowKey;
    }

    function rowIdentityKeys(context = state.context) {
        if (!context || typeof context !== 'object') {
            return [];
        }

        if (Array.isArray(context.row_identity_keys) && context.row_identity_keys.length > 0) {
            return context.row_identity_keys;
        }

        return Array.isArray(context.primary_keys) ? context.primary_keys : [];
    }

    function rowActionMeta(context = state.context) {
        if (!context || typeof context !== 'object' || !context.row_actions || typeof context.row_actions !== 'object') {
            return {
                supported: rowIdentityKeys(context).length > 0,
                source: rowIdentityKeys(context).length > 0 ? 'primary' : 'none',
                label: rowIdentityKeys(context).length > 0 ? 'Primary key' : 'Không có row key',
                reason: rowIdentityKeys(context).length > 0 ? '' : 'Bảng này không có row key hợp lệ để sửa/xóa trực tiếp.',
            };
        }

        return context.row_actions;
    }

    function supportsRowMutations(context = state.context) {
        const meta = rowActionMeta(context);
        return !!(meta && meta.supported && rowIdentityKeys(context).length > 0);
    }

    function rowIdentityLabel(context = state.context) {
        const keys = rowIdentityKeys(context);
        if (keys.length === 0) {
            return 'Row key: -';
        }

        const meta = rowActionMeta(context);
        const prefix = meta.source === 'unique' ? 'Unique key' : 'Primary key';
        return `${prefix}: ${keys.join(', ')}`;
    }

    function cellKey(rowIndex, columnName) {
        return `${rowIndex}::${columnName}`;
    }

    function clearCellStatus(rowIndex, columnName, delay = 1200) {
        const key = cellKey(rowIndex, columnName);
        window.setTimeout(() => {
            if (state.cellStatus[key] && state.cellStatus[key].until) {
                delete state.cellStatus[key];
                renderDataPanel();
            }
        }, delay);
    }

    function databaseStorageName() {
        return bootstrapData.database_name || 'database';
    }

    function columnWidthStorageKey() {
        return `sqlm:columnWidths:${databaseStorageName()}:${state.selectedTable || 'table'}`;
    }

    function loadColumnWidths() {
        try {
            const raw = window.localStorage.getItem(columnWidthStorageKey());
            state.columnWidths = raw ? JSON.parse(raw) : {};
        } catch (error) {
            state.columnWidths = {};
        }
    }

    function saveColumnWidths() {
        try {
            window.localStorage.setItem(columnWidthStorageKey(), JSON.stringify(state.columnWidths || {}));
        } catch (error) {
            /* localStorage can fail in private mode; resizing still works for this session. */
        }
    }

    function defaultColumnWidth(column) {
        const name = String(column.name || '').toLowerCase();
        const type = String(column.column_type || column.data_type || '').toLowerCase();
        const declaredLength = declaredColumnLength(column);

        if (isMultilineColumn(column) || /content|description|meta|json|body|message|payload|config|settings|notes?/.test(name)) return 360;
        if (/url|link|image|avatar|thumbnail|path|file/.test(name)) return 260;
        if (/token|secret|hash|uuid|guid|api_key|access_key/.test(name)) return 240;
        if (/email/.test(name)) return 220;
        if (type.includes('datetime') || type.includes('timestamp')) return 178;
        if (type.includes('date') || type.includes('time')) return 136;
        if (isBooleanColumn(column)) return 92;
        if (isNumericColumn(column)) return /(^id$|_id$|id$)/.test(name) ? 112 : 104;
        if (/enum|set/.test(type)) return 150;
        if (/varchar|char/.test(type)) {
            if (declaredLength >= 191) return 230;
            if (declaredLength >= 100) return 200;
            if (declaredLength >= 60) return 170;
        }

        return Math.max(132, Math.min(240, String(column.name || '').length * 10 + 64));
    }

    function declaredColumnLength(column) {
        const match = String(column.column_type || column.data_type || '').match(/\((\d+)/);
        return match ? Number(match[1]) || 0 : 0;
    }

    function staticColumnMeta(columnName) {
        const map = {
            __select__: { defaultWidth: 56, minWidth: 48, maxWidth: 180 },
            __row_index__: { defaultWidth: 72, minWidth: 48, maxWidth: 220 },
            __actions__: { defaultWidth: 92, minWidth: 60, maxWidth: 220 },
        };
        return map[columnName] || null;
    }

    function minColumnWidth(columnName) {
        const staticMeta = staticColumnMeta(columnName);
        return staticMeta ? staticMeta.minWidth : 60;
    }

    function maxColumnWidth(columnName) {
        const staticMeta = staticColumnMeta(columnName);
        return staticMeta ? staticMeta.maxWidth : 640;
    }

    function resolveColumnWidth(columnName) {
        const staticMeta = staticColumnMeta(columnName);
        if (staticMeta) {
            const savedStaticWidth = Number(state.columnWidths[columnName]);
            if (Number.isFinite(savedStaticWidth) && savedStaticWidth > 0) {
                return savedStaticWidth;
            }
            return staticMeta.defaultWidth;
        }

        const column = columnByName(columnName);
        return column ? columnWidth(column) : 0;
    }

    function columnWidth(column) {
        const saved = Number(state.columnWidths[column.name]);
        if (Number.isFinite(saved) && saved > 0) {
            return saved;
        }

        return defaultColumnWidth(column);
    }

    function setColumnWidth(columnName, width) {
        state.columnWidths[columnName] = Math.max(minColumnWidth(columnName), Math.min(maxColumnWidth(columnName), Math.round(Number(width) || 0)));
        saveColumnWidths();
        applyColumnWidths();
    }

    function resetColumnWidths() {
        state.columnWidths = {};
        saveColumnWidths();
        renderDataPanel();
    }

    function autoFitColumn(columnName) {
        if (!state.context) return;
        const staticMeta = staticColumnMeta(columnName);
        if (staticMeta) {
            if (columnName === '__select__') {
                setColumnWidth(columnName, 56);
                return;
            }
            if (columnName === '__actions__') {
                setColumnWidth(columnName, 92);
                return;
            }
            if (columnName === '__row_index__') {
                const pagination = state.context.pagination || {};
                const rows = Array.isArray(state.context.rows) ? state.context.rows : [];
                const start = Number(pagination.offset) || 0;
                const maxIndex = start + rows.length;
                const hasPending = rows.some((row) => row && row.__sqlmPending);
                const estimated = Math.max(48, String(Math.max(maxIndex, 1)).length * 9 + (hasPending ? 64 : 36));
                setColumnWidth(columnName, estimated);
                return;
            }
        }

        const column = state.context.columns.find((item) => item.name === columnName);
        if (!column) return;
        let longest = String(columnName).length;
        state.context.rows.forEach((row) => {
            longest = Math.max(longest, normalizePreviewValue(row[columnName]).length);
        });
        setColumnWidth(columnName, Math.min(maxColumnWidth(columnName), Math.max(minColumnWidth(columnName), longest * 7 + 34)));
    }

    function autoFitAllColumns() {
        if (!state.context) return;
        state.context.columns.forEach((column) => autoFitColumn(column.name));
    }

    function applyColumnWidths() {
        if (!refs.dataPanel || !state.context) return;
        ['__select__', '__row_index__', '__actions__'].forEach((columnName) => {
            const width = `${resolveColumnWidth(columnName)}px`;
            refs.dataPanel.querySelectorAll(`[data-col-name="${selectorEscape(columnName)}"]`).forEach((node) => {
                node.style.width = width;
                node.style.minWidth = width;
                node.style.maxWidth = width;
            });
        });
        state.context.columns.forEach((column) => {
            const width = `${columnWidth(column)}px`;
            refs.dataPanel.querySelectorAll(`[data-col-name="${selectorEscape(column.name)}"]`).forEach((node) => {
                node.style.width = width;
                node.style.minWidth = width;
                node.style.maxWidth = width;
            });
        });
        applyTableWidth();
    }

    function tablePixelWidth() {
        if (!state.context || !Array.isArray(state.context.columns)) {
            return 0;
        }

        const staticWidth = resolveColumnWidth('__select__') + resolveColumnWidth('__row_index__') + resolveColumnWidth('__actions__');
        const dataWidth = state.context.columns.reduce((total, column) => total + columnWidth(column), 0);
        const columnCount = state.context.columns.length;
        const averageColumnFloor = columnCount >= 8 ? 148 : (columnCount >= 5 ? 136 : 112);
        const columnCountFloor = staticWidth + (columnCount * averageColumnFloor);
        return Math.ceil(Math.max(staticWidth + dataWidth, columnCountFloor) + columnCount + 12);
    }

    function applyTableWidth() {
        if (!refs.dataPanel || !state.context) return;
        const table = refs.dataPanel.querySelector('.sqlm-grid-table');
        const wrap = refs.dataPanel.querySelector('.sqlm-grid-wrap');
        if (!table || !wrap) return;

        const width = Math.max(tablePixelWidth(), Math.ceil(wrap.clientWidth || 0) + 1);
        wrap.style.setProperty('--sqlm-grid-width', `${width}px`);
        table.style.setProperty('--sqlm-grid-width', `${width}px`);
        table.style.width = `${width}px`;
        table.style.minWidth = `${width}px`;
        table.style.maxWidth = 'none';
    }

    function estimateStandaloneGridColumnWidth(text, isFirstColumn = false) {
        const length = String(text || '').trim().length;
        const min = isFirstColumn ? 64 : 112;
        const max = /json|payload|content|description|statement|message/i.test(String(text || '')) ? 420 : 360;
        return Math.max(min, Math.min(max, length * 7 + 34));
    }

    function applyStandaloneGridWidths(root) {
        if (!root) return;
        root.querySelectorAll('.sqlm-grid-wrap').forEach((wrap) => {
            if (refs.dataPanel && refs.dataPanel.contains(wrap)) return;
            const table = wrap.querySelector('.sqlm-grid-table');
            if (!table) return;

            const headers = Array.from(table.querySelectorAll('thead th'));
            const bodyRows = Array.from(table.querySelectorAll('tbody tr')).slice(0, 80);
            const columnCount = headers.length || bodyRows.reduce((count, row) => Math.max(count, row.children.length), 0);
            if (columnCount <= 0) return;

            let contentWidth = 0;
            for (let index = 0; index < columnCount; index += 1) {
                const samples = [
                    headers[index] ? headers[index].textContent : '',
                    ...bodyRows.map((row) => row.children[index] ? row.children[index].textContent : ''),
                ];
                const longest = samples.reduce((max, text) => {
                    return String(text || '').trim().length > String(max || '').trim().length ? text : max;
                }, '');
                contentWidth += estimateStandaloneGridColumnWidth(longest, index === 0);
            }

            const width = Math.max(
                Math.ceil(contentWidth + columnCount + 12),
                Math.ceil(columnCount * (columnCount >= 8 ? 142 : 118) + 24),
                Math.ceil(wrap.clientWidth || 0) + 1
            );

            wrap.style.setProperty('--sqlm-grid-width', `${width}px`);
            table.style.setProperty('--sqlm-grid-width', `${width}px`);
            table.style.width = `${width}px`;
            table.style.minWidth = `${width}px`;
            table.style.maxWidth = 'none';
        });
    }

    function refreshGridWidths() {
        applyColumnWidths();
        applyStandaloneGridWidths(refs.structurePanel);
        applyStandaloneGridWidths(refs.queryPanel);
    }

    function applyExplorerWidth() {
        const width = Math.max(220, Math.min(600, Number(state.explorerWidth) || 286));
        state.explorerWidth = width;
        app.style.setProperty('--sqlm-explorer-width', `${width}px`);
    }

    function setSelectedRow(rowIndex, preferredColumn = '') {
        if (!state.context || !Array.isArray(state.context.rows)) {
            state.selectedRowIndex = null;
            return;
        }

        const normalizedIndex = Number(rowIndex);
        if (!Number.isInteger(normalizedIndex) || normalizedIndex < 0 || normalizedIndex >= state.context.rows.length) {
            state.selectedRowIndex = null;
            return;
        }

        state.selectedRowIndex = normalizedIndex;
        const fallbackColumn = preferredColumn || state.cellPreview.column || (state.context.columns[0] ? state.context.columns[0].name : '');
        if (fallbackColumn) {
            setCellPreview(normalizedIndex, fallbackColumn);
        }
    }

    function modeConfig(tabName) {
        const modes = {
            data: {
                badge: 'Data',
                title: 'Dữ liệu',
                description: 'Duyệt grid dữ liệu, chọn dòng, xem nhanh giá trị dài và thao tác CRUD theo bảng hiện tại.',
            },
            structure: {
                badge: 'Structure',
                title: 'Cấu trúc',
                description: 'Xem cột, kiểu dữ liệu, khóa và metadata của bảng theo kiểu structure browser.',
            },
            query: {
                badge: 'SQL',
                title: 'SQL Console',
                description: 'Chạy truy vấn đọc dữ liệu và xem kết quả trong cùng workspace.',
            },
            search: {
                badge: 'Search',
                title: 'Tìm kiếm',
                description: 'Dùng bộ lọc backend hiện có để tìm nhanh theo nội dung bảng và sort cột.',
            },
            insert: {
                badge: 'Insert',
                title: 'Chèn',
                description: 'Tạo bản ghi mới bằng form insert hiện có hoặc sinh dữ liệu mẫu để thao tác nhanh.',
            },
            export: {
                badge: 'Export',
                title: 'Xuất',
                description: 'Xuất dữ liệu trang hiện tại hoặc dựng chart nhanh từ dataset đang mở.',
            },
            import: {
                badge: 'Import',
                title: 'Nhập',
                description: 'Nhập SQL, CSV hoặc JSON. File SQL sẽ chạy qua preflight, báo rollback thực tế và module health bị ảnh hưởng.',
            },
            operations: {
                badge: 'Ops',
                title: 'Thao tác',
                description: 'Transaction thật đã có backend. Các action quản trị bảng nâng cao được hiển thị ở dạng shell/disabled.',
            },
        };

        return modes[tabName] || modes.data;
    }

    function truncateCellText(value, maxLength = 88) {
        const text = value === null || value === undefined ? 'NULL' : String(value);
        if (text.length <= maxLength) {
            return text;
        }

        return `${text.slice(0, maxLength - 1)}…`;
    }

    function normalizePreviewValue(value) {
        if (value === null || value === undefined) {
            return 'NULL';
        }

        return typeof value === 'string' ? value : JSON.stringify(value, null, 2);
    }

    function setCellPreview(rowIndex, columnName) {
        const row = state.context && Array.isArray(state.context.rows) ? state.context.rows[rowIndex] : null;
        state.cellPreview = {
            rowIndex,
            column: columnName || '',
            value: row && columnName ? normalizePreviewValue(row[columnName]) : '',
        };
    }

    function resolvePreviewPayload() {
        const row = selectedRow();
        const previewColumn = state.cellPreview.column || (state.context && state.context.columns[0] ? state.context.columns[0].name : '');

        if (!row) {
            return {
                rowLabel: 'Chưa chọn dòng',
                columnLabel: 'Chưa chọn ô',
                value: 'Chọn một dòng hoặc một ô dài để xem đầy đủ nội dung.',
            };
        }

        const rowNumber = state.context && state.context.pagination
            ? Number(state.context.pagination.offset || 0) + Number(state.selectedRowIndex || 0) + 1
            : Number(state.selectedRowIndex || 0) + 1;

        const value = previewColumn !== '' ? normalizePreviewValue(row[previewColumn]) : 'Không có dữ liệu.';

        return {
            rowLabel: `Dòng ${rowNumber}`,
            columnLabel: previewColumn !== '' ? previewColumn : 'Không rõ cột',
            value,
        };
    }

    function selectedPendingInsertOperation() {
        const row = selectedRow();
        if (!row || !row.__sqlmOperationId) {
            return null;
        }

        return state.transaction.queue.find((operation) => operation.id === row.__sqlmOperationId && operation.type === 'insert') || null;
    }

    function createOperationId() {
        state.transaction.localCounter += 1;
        return `local-op-${Date.now()}-${state.transaction.localCounter}`;
    }

    function normalizeOperationPayload(operation) {
        const payload = {
            type: operation.type,
            table: operation.table,
        };

        if (operation.row_key) {
            payload.row_key = operation.row_key;
        }

        if (operation.values) {
            payload.values = operation.values;
        }

        return payload;
    }

    function normalizeLocalValue(payload) {
        if (payload && typeof payload === 'object' && payload.is_null) {
            return null;
        }

        return payload && typeof payload === 'object' ? payload.value : payload;
    }

    function isGeneratedColumn(column) {
        return /generated/i.test(String(column.extra || ''));
    }

    function isNumericColumn(column) {
        return /int|decimal|numeric|float|double|real|bit/i.test(String(column.data_type || column.column_type || ''));
    }

    function isMultilineColumn(column) {
        return /text|json/i.test(String(column.data_type || '')) || /text|json/i.test(String(column.column_type || ''));
    }

    function isDateColumn(column) {
        return /date|time|timestamp/i.test(String(column.data_type || column.column_type || ''));
    }

    function isBooleanColumn(column) {
        const name = String(column.name || '').toLowerCase();
        const type = String(column.column_type || column.data_type || '').toLowerCase();
        return type === 'tinyint(1)' || type === 'bit(1)' || name.startsWith('is_') || name.startsWith('has_') || name.startsWith('can_') || name.includes('enabled');
    }

    function enumOptions(column) {
        const type = String(column.column_type || '');
        const match = type.match(/^enum\((.*)\)$/i);
        if (!match) {
            return [];
        }

        const options = [];
        const regex = /'((?:\\'|[^'])*)'/g;
        let item = regex.exec(match[1]);
        while (item) {
            options.push(item[1].replace(/\\'/g, "'"));
            item = regex.exec(match[1]);
        }
        return options;
    }

    function isJsonColumn(column) {
        return /json/i.test(String(column.data_type || column.column_type || '')) || /json|meta/i.test(String(column.name || ''));
    }

    function isReadonlyColumn(column) {
        return isGeneratedColumn(column);
    }

    function editorInputType(column) {
        const type = String(column.data_type || column.column_type || '').toLowerCase();
        if (type === 'date') return 'date';
        if (type.includes('datetime') || type.includes('timestamp')) return 'datetime-local';
        if (type === 'time') return 'time';
        if (isNumericColumn(column) && !isBooleanColumn(column)) return 'number';
        return 'text';
    }

    function valueForEditor(value, column) {
        if (value === null || value === undefined) {
            return '';
        }

        const text = String(value);
        const inputType = editorInputType(column);
        if (inputType === 'datetime-local') {
            return text.replace(' ', 'T').slice(0, 16);
        }

        if (inputType === 'date') {
            return text.slice(0, 10);
        }

        if (inputType === 'time') {
            return text.slice(0, 8);
        }

        return text;
    }

    function valueFromEditor(value, column) {
        const inputType = editorInputType(column);
        if (inputType === 'datetime-local') {
            return String(value || '').replace('T', ' ') + (String(value || '').length === 16 ? ':00' : '');
        }

        return value;
    }

    function toDisplayString(value) {
        if (value === null || value === undefined) {
            return '';
        }

        return typeof value === 'object' ? JSON.stringify(value) : String(value);
    }

    function findRowIndexByKey(rowKey) {
        if (!state.context || !Array.isArray(state.context.rows) || !rowKey) {
            return -1;
        }

        return state.context.rows.findIndex((row) => {
            if (!row || !row.__sqlmRowKey) {
                return false;
            }

            return Object.keys(rowKey).every((key) => String(row.__sqlmRowKey[key]) === String(rowKey[key]));
        });
    }

    function rowKeyForRow(row) {
        if (!row || !state.context) {
            return null;
        }

        if (row.__sqlmRowKey && typeof row.__sqlmRowKey === 'object') {
            return row.__sqlmRowKey;
        }

        const keys = rowIdentityKeys(state.context);
        if (keys.length === 0) {
            return null;
        }

        const rowKey = {};
        keys.forEach((key) => {
            rowKey[key] = row[key];
        });
        return rowKey;
    }

    function columnByName(columnName) {
        return state.context && Array.isArray(state.context.columns)
            ? state.context.columns.find((column) => column.name === columnName) || null
            : null;
    }

    function renderCellDisplay(row, rowIndex, column) {
        const rawValue = row[column.name];
        const fullText = normalizePreviewValue(rawValue);
        const shortText = truncateCellText(fullText);
        const isLong = fullText.length > 88 || isMultilineColumn(column);
        return `
            <button type="button" class="sqlm-cell-button" data-cell-edit="${rowIndex}" data-row-index="${rowIndex}" data-column="${escapeHtml(column.name)}">
                <span class="sqlm-cell-text">${rawValue === null || rawValue === undefined ? '<span class="sqlm-null">(NULL)</span>' : (shortText === '' ? '<span class="text-secondary">(empty)</span>' : escapeHtml(shortText))}</span>
                ${isLong ? '<span class="sqlm-cell-more" title="Alt+Enter mở editor lớn">↗</span>' : ''}
            </button>
        `;
    }

    function renderCellEditor(row, rowIndex, column) {
        const value = row[column.name];
        const isNull = value === null || value === undefined;
        const common = `data-inline-editor="1" data-row-index="${rowIndex}" data-column="${escapeHtml(column.name)}"`;
        const options = enumOptions(column);

        if (options.length > 0) {
            return `
                <select class="sqlm-inline-editor" ${common}>
                    ${column.is_nullable ? `<option value="__SQLM_NULL__" ${isNull ? 'selected' : ''}>(NULL)</option>` : ''}
                    ${options.map((option) => `<option value="${escapeHtml(option)}" ${String(value ?? '') === option ? 'selected' : ''}>${escapeHtml(option)}</option>`).join('')}
                </select>
            `;
        }

        if (isBooleanColumn(column)) {
            const checked = ['1', 'true', 'yes', 'on'].includes(String(value ?? '').toLowerCase());
            return `
                <select class="sqlm-inline-editor" ${common}>
                    ${column.is_nullable ? `<option value="__SQLM_NULL__" ${isNull ? 'selected' : ''}>(NULL)</option>` : ''}
                    <option value="1" ${checked && !isNull ? 'selected' : ''}>true / 1</option>
                    <option value="0" ${!checked && !isNull ? 'selected' : ''}>false / 0</option>
                </select>
            `;
        }

        if (isMultilineColumn(column)) {
            return `
                <div class="sqlm-inline-textarea-wrap">
                    <textarea class="sqlm-inline-editor sqlm-inline-textarea ${isJsonColumn(column) ? 'is-json' : ''}" rows="1" ${common}>${escapeHtml(valueForEditor(value, column))}</textarea>
                    ${isJsonColumn(column) ? '<button type="button" class="sqlm-format-json" data-format-json>Format JSON</button>' : ''}
                </div>
            `;
        }

        return `<input class="sqlm-inline-editor" type="${editorInputType(column)}" value="${escapeHtml(valueForEditor(value, column))}" ${common}>`;
    }

    function cellClass(rowIndex, columnName) {
        const status = state.cellStatus[cellKey(rowIndex, columnName)] || {};
        const editing = state.inlineEdit && state.inlineEdit.rowIndex === rowIndex && state.inlineEdit.column === columnName;
        return [
            'sqlm-cell',
            editing ? 'is-editing' : '',
            status.state ? `is-${status.state}` : '',
        ].filter(Boolean).join(' ');
    }

    function beginInlineEdit(rowIndex, columnName) {
        if (!state.context || hasActiveTransaction()) {
            if (hasActiveTransaction()) showFlash('Inline edit tạm tắt trong transaction mode. Hãy commit/rollback trước.', 'warning');
            return;
        }

        const row = state.context.rows[rowIndex];
        const column = columnByName(columnName);
        if (!row || !column) return;

        if (!supportsRowMutations(state.context)) {
            showFlash(rowActionMeta(state.context).reason || 'Bảng này không có row key để update inline.', 'warning');
            return;
        }

        if (isReadonlyColumn(column)) {
            showFlash('Cột generated/readonly không thể sửa inline.', 'warning');
            return;
        }

        if (rowIdentityKeys(state.context).includes(columnName) && !window.confirm('Bạn đang sửa primary/unique key. Tiếp tục?')) {
            return;
        }

        state.inlineEdit = {
            rowIndex,
            column: columnName,
            originalValue: row[columnName],
            saving: false,
        };
        setSelectedRow(rowIndex, columnName);
        renderDataPanel();
        window.setTimeout(() => {
            const editor = refs.dataPanel.querySelector('[data-inline-editor]');
            if (editor) {
                editor.focus();
                if (typeof editor.select === 'function') editor.select();
                autoResizeInlineEditor(editor);
            }
        }, 0);
    }

    function autoResizeInlineEditor(editor) {
        if (!editor || editor.tagName !== 'TEXTAREA') return;
        editor.style.height = 'auto';
        editor.style.height = `${Math.min(220, Math.max(30, editor.scrollHeight))}px`;
    }

    function cancelInlineEdit() {
        state.inlineEdit = null;
        renderDataPanel();
    }

    function inlinePayloadFromEditor(editor, column) {
        let value = editor.value;
        let isNull = value === '__SQLM_NULL__';
        if (isNull) {
            value = '';
        } else {
            value = valueFromEditor(value, column);
        }

        if (isJsonColumn(column) && !isNull && String(value).trim() !== '') {
            JSON.parse(String(value));
        }

        return { value, is_null: isNull };
    }

    async function saveInlineEditor(editor, moveDirection = 0) {
        if (!state.inlineEdit || state.inlineEdit.saving || !editor) return;

        const { rowIndex, column: columnName, originalValue } = state.inlineEdit;
        const row = state.context.rows[rowIndex];
        const column = columnByName(columnName);
        const rowKey = rowKeyForRow(row);
        if (!row || !column || !rowKey) {
            showFlash('Không xác định được row key để lưu inline.', 'danger');
            return;
        }

        let payload;
        try {
            payload = inlinePayloadFromEditor(editor, column);
        } catch (error) {
            state.cellStatus[cellKey(rowIndex, columnName)] = { state: 'error', message: 'JSON không hợp lệ.' };
            renderDataPanel();
            showFlash('JSON không hợp lệ, chưa lưu.', 'danger');
            return;
        }

        const nextValue = payload.is_null ? null : payload.value;
        if (String(nextValue ?? '') === String(originalValue ?? '')) {
            state.inlineEdit = null;
            renderDataPanel();
            moveInlineSelection(rowIndex, columnName, moveDirection);
            return;
        }

        const params = new URLSearchParams();
        params.set('_csrf', state.csrfToken);
        params.set('table', state.context.table);
        params.set('row_key_json', JSON.stringify(rowKey));
        params.set('values_json', JSON.stringify({ [columnName]: payload }));

        try {
            state.inlineEdit.saving = true;
            state.cellStatus[cellKey(rowIndex, columnName)] = { state: 'dirty' };
            renderDataPanel();
            const result = await requestJson(endpoints.update, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-TOKEN': state.csrfToken,
                },
                body: params.toString(),
            });

            row[columnName] = nextValue;
            if (row.__sqlmRowKey && Object.prototype.hasOwnProperty.call(row.__sqlmRowKey, columnName)) {
                row.__sqlmRowKey[columnName] = nextValue;
            }
            state.inlineEdit = null;
            state.cellStatus[cellKey(rowIndex, columnName)] = { state: 'saved', until: Date.now() + 1000 };
            renderDataPanel();
            clearCellStatus(rowIndex, columnName, 1000);
            showFlash(result.message || 'Đã lưu ô dữ liệu.', 'success');
            moveInlineSelection(rowIndex, columnName, moveDirection);
        } catch (error) {
            state.inlineEdit = null;
            state.cellStatus[cellKey(rowIndex, columnName)] = { state: 'error', message: error.message };
            renderDataPanel();
            showFlash(error.message, 'danger');
        }
    }

    function moveInlineSelection(rowIndex, columnName, direction) {
        if (!direction || !state.context) return;
        const columns = state.context.columns || [];
        const index = columns.findIndex((column) => column.name === columnName);
        if (index < 0) return;
        let nextRow = rowIndex;
        let nextColumnIndex = index + direction;
        if (nextColumnIndex >= columns.length) {
            nextColumnIndex = 0;
            nextRow += 1;
        } else if (nextColumnIndex < 0) {
            nextColumnIndex = columns.length - 1;
            nextRow -= 1;
        }
        if (nextRow < 0 || nextRow >= state.context.rows.length) return;
        beginInlineEdit(nextRow, columns[nextColumnIndex].name);
    }

    function closeContextMenu() {
        document.querySelectorAll('.sqlm-context-menu').forEach((node) => node.remove());
    }

    function openLargeEditor(editor) {
        const column = columnByName(editor.getAttribute('data-column') || '');
        const overlay = document.createElement('div');
        overlay.className = 'sqlm-large-editor';
        overlay.innerHTML = `
            <div class="sqlm-large-editor-box">
                <div class="sqlm-large-editor-head">
                    <strong>${escapeHtml(column ? column.name : 'Cell editor')}</strong>
                    <button type="button" data-large-close>×</button>
                </div>
                <textarea spellcheck="false">${escapeHtml(editor.value || '')}</textarea>
                <div class="sqlm-large-editor-actions">
                    ${column && isJsonColumn(column) ? '<button type="button" class="btn btn-light border btn-sm" data-large-format>Format JSON</button>' : ''}
                    <button type="button" class="btn btn-light border btn-sm" data-large-cancel>Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" data-large-apply>Apply</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        const area = overlay.querySelector('textarea');
        area.focus();
        overlay.querySelectorAll('[data-large-close], [data-large-cancel]').forEach((button) => {
            button.addEventListener('click', () => {
                overlay.remove();
                editor.focus();
            });
        });
        const formatButton = overlay.querySelector('[data-large-format]');
        if (formatButton) {
            formatButton.addEventListener('click', () => {
                try {
                    area.value = JSON.stringify(JSON.parse(area.value || '{}'), null, 2);
                } catch (error) {
                    showFlash('JSON không hợp lệ nên chưa format được.', 'danger');
                }
            });
        }
        overlay.querySelector('[data-large-apply]').addEventListener('click', () => {
            editor.value = area.value;
            autoResizeInlineEditor(editor);
            overlay.remove();
            editor.focus();
        });
    }

    async function setInlineCellValue(rowIndex, columnName, value, isNull = false) {
        const row = state.context && state.context.rows ? state.context.rows[rowIndex] : null;
        const column = columnByName(columnName);
        if (!row || !column) return;
        if (isNull && !column.is_nullable) {
            showFlash('Cột này không cho phép NULL.', 'warning');
            return;
        }
        state.inlineEdit = { rowIndex, column: columnName, originalValue: row[columnName], saving: false };
        const fakeEditor = { value: isNull ? '__SQLM_NULL__' : String(value ?? ''), tagName: 'INPUT' };
        await saveInlineEditor(fakeEditor);
    }

    function openCellContextMenu(event, rowIndex, columnName) {
        event.preventDefault();
        closeContextMenu();
        if (!state.context) return;

        const row = state.context.rows[rowIndex];
        const value = row ? row[columnName] : '';
        const menu = document.createElement('div');
        menu.className = 'sqlm-context-menu';
        menu.style.left = `${event.clientX}px`;
        menu.style.top = `${event.clientY}px`;
        menu.innerHTML = `
            <button type="button" data-menu-action="edit"><i class="fas fa-pen"></i>Edit Cell</button>
            <button type="button" data-menu-action="null"><i class="fas fa-ban"></i>Set NULL</button>
            <button type="button" data-menu-action="copy"><i class="fas fa-copy"></i>Copy Value</button>
            <button type="button" data-menu-action="paste"><i class="fas fa-paste"></i>Paste Value</button>
            <button type="button" data-menu-action="revert"><i class="fas fa-rotate-left"></i>Revert Changes</button>
        `;
        document.body.appendChild(menu);

        menu.querySelectorAll('[data-menu-action]').forEach((button) => {
            button.addEventListener('click', async () => {
                const action = button.getAttribute('data-menu-action');
                closeContextMenu();
                try {
                    if (action === 'edit') {
                        beginInlineEdit(rowIndex, columnName);
                    } else if (action === 'null') {
                        await setInlineCellValue(rowIndex, columnName, '', true);
                    } else if (action === 'copy') {
                        await navigator.clipboard?.writeText(normalizePreviewValue(value));
                        showFlash('Đã copy giá trị ô.', 'success');
                    } else if (action === 'paste') {
                        const text = await navigator.clipboard?.readText();
                        await setInlineCellValue(rowIndex, columnName, text || '', false);
                    } else if (action === 'revert') {
                        delete state.cellStatus[cellKey(rowIndex, columnName)];
                        state.inlineEdit = null;
                        renderDataPanel();
                    }
                } catch (error) {
                    showFlash(error.message || 'Không thao tác được clipboard.', 'danger');
                }
            });
        });
    }

    function startColumnResize(event) {
        const columnName = event.currentTarget.getAttribute('data-col-resize') || '';
        if (!columnName) return;
        const startWidth = resolveColumnWidth(columnName);
        if (startWidth <= 0) return;
        event.preventDefault();
        event.stopPropagation();
        const handle = event.currentTarget;
        if (event.pointerId !== undefined && handle && typeof handle.setPointerCapture === 'function') {
            try {
                handle.setPointerCapture(event.pointerId);
            } catch (error) {
                /* ignore capture failure */
            }
        }
        state.resize = {
            type: 'column',
            column: columnName,
            startX: event.clientX,
            startWidth,
            handle,
            pointerId: event.pointerId,
        };
        document.body.classList.add('sqlm-is-resizing');
        showResizeGuide(event.clientX);
        window.addEventListener('pointermove', handleResizeMove);
        window.addEventListener('pointerup', finishResize, { once: true });
        window.addEventListener('pointercancel', finishResize, { once: true });
    }

    function startExplorerResize(event) {
        if (state.explorerCollapsed) return;
        event.preventDefault();
        state.resize = {
            type: 'explorer',
            startX: event.clientX,
            startWidth: state.explorerWidth,
        };
        document.body.classList.add('sqlm-is-resizing');
        showResizeGuide(event.clientX);
        window.addEventListener('pointermove', handleResizeMove);
        window.addEventListener('pointerup', finishResize, { once: true });
    }

    function showResizeGuide(x) {
        let guide = document.querySelector('.sqlm-resize-guide');
        if (!guide) {
            guide = document.createElement('div');
            guide.className = 'sqlm-resize-guide';
            document.body.appendChild(guide);
        }
        guide.style.left = `${x}px`;
    }

    function handleResizeMove(event) {
        if (!state.resize) return;
        showResizeGuide(event.clientX);
        const delta = event.clientX - state.resize.startX;
        if (state.resize.type === 'column') {
            setColumnWidth(state.resize.column, state.resize.startWidth + delta);
        } else if (state.resize.type === 'explorer') {
            state.explorerWidth = Math.max(220, Math.min(600, state.resize.startWidth + delta));
            applyExplorerWidth();
        }
    }

    function finishResize() {
        if (state.resize && state.resize.type === 'column' && state.resize.handle && state.resize.pointerId !== undefined) {
            try {
                if (typeof state.resize.handle.releasePointerCapture === 'function') {
                    state.resize.handle.releasePointerCapture(state.resize.pointerId);
                }
            } catch (error) {
                /* ignore capture release failure */
            }
        }
        if (state.resize && state.resize.type === 'explorer') {
            try {
                window.localStorage.setItem('sqlm:explorerWidth', String(state.explorerWidth));
            } catch (error) {
                /* ignore */
            }
        }
        state.resize = null;
        document.body.classList.remove('sqlm-is-resizing');
        document.querySelectorAll('.sqlm-resize-guide').forEach((node) => node.remove());
        window.removeEventListener('pointermove', handleResizeMove);
        window.removeEventListener('pointercancel', finishResize);
    }

    function moveCellByArrow(event) {
        if (!state.context || state.activeTab !== 'data' || state.inlineEdit) return;
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName || '')) return;
        const columns = state.context.columns || [];
        if (columns.length === 0) return;
        const currentRow = state.selectedRowIndex === null ? 0 : state.selectedRowIndex;
        const currentColumn = state.cellPreview.column || columns[0].name;
        let rowIndex = currentRow;
        let columnIndex = Math.max(0, columns.findIndex((column) => column.name === currentColumn));
        if (event.key === 'ArrowRight') columnIndex = Math.min(columns.length - 1, columnIndex + 1);
        else if (event.key === 'ArrowLeft') columnIndex = Math.max(0, columnIndex - 1);
        else if (event.key === 'ArrowDown') rowIndex = Math.min(state.context.rows.length - 1, rowIndex + 1);
        else if (event.key === 'ArrowUp') rowIndex = Math.max(0, rowIndex - 1);
        else return;
        event.preventDefault();
        setSelectedRow(rowIndex, columns[columnIndex].name);
        renderDataPanel();
        updateToolbarMeta();
    }

    function buildPreviewRow(values, operationId) {
        const row = {};

        state.context.columns.forEach((column) => {
            if (Object.prototype.hasOwnProperty.call(values, column.name)) {
                row[column.name] = normalizeLocalValue(values[column.name]);
            } else if (column.default_value !== null && column.default_value !== undefined) {
                row[column.name] = column.default_value;
            } else if (/auto_increment/i.test(String(column.extra || ''))) {
                row[column.name] = '(auto)';
            } else {
                row[column.name] = column.is_nullable ? null : '';
            }
        });

        row.__sqlmPending = true;
        row.__sqlmPreview = true;
        row.__sqlmOperationId = operationId;
        row.__sqlmRowKey = null;

        return row;
    }

    async function requestJson(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(options.headers || {}),
            },
            ...options,
        });

        const text = await response.text();
        let payload = null;

        try {
            payload = JSON.parse(text);
        } catch (error) {
            throw new Error('Server trả về dữ liệu không hợp lệ.');
        }

        if (payload && payload.csrf_token) {
            state.csrfToken = payload.csrf_token;
        }

        if (!response.ok || payload.success === false) {
            throw new Error(payload && payload.message ? payload.message : 'Yêu cầu thất bại.');
        }

        return payload;
    }

    async function requestImport(file, confirmRisky = false) {
        const formData = new FormData();
        formData.append('_csrf', state.csrfToken);
        formData.append('sql_file', file, file.name);
        if (confirmRisky) {
            formData.append('confirm_risky', '1');
        }

        const response = await fetch(endpoints.import, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': state.csrfToken,
            },
            body: formData,
        });

        const text = await response.text();
        let payload = null;

        try {
            payload = JSON.parse(text);
        } catch (error) {
            throw new Error('Server trả về dữ liệu import không hợp lệ.');
        }

        if (payload && payload.csrf_token) {
            state.csrfToken = payload.csrf_token;
        }

        return {
            ok: response.ok && payload.success === true,
            status: response.status,
            payload,
        };
    }

    function healthBadgeClass(moduleStatus) {
        return moduleStatus && moduleStatus.healthy ? 'is-healthy' : 'is-unhealthy';
    }

    function formatModuleHealthCards(summary, moduleKeys = []) {
        const modules = summary && summary.modules && typeof summary.modules === 'object'
            ? summary.modules
            : {};
        const keys = moduleKeys.length > 0 ? moduleKeys : Object.keys(modules);

        if (keys.length === 0) {
            return '<div class="sqlm-empty">Chưa có dữ liệu health check.</div>';
        }

        return `
            <div class="sqlm-health-grid">
                ${keys.map((key) => {
                    const item = modules[key];
                    if (!item) {
                        return '';
                    }

                    const firstIssue = Array.isArray(item.issues) && item.issues[0]
                        ? item.issues[0].message
                        : 'Schema toàn vẹn.';

                    return `
                        <article class="sqlm-health-card ${healthBadgeClass(item)}">
                            <div class="sqlm-health-head">
                                <strong>${escapeHtml(item.label || key)}</strong>
                                <span class="sqlm-health-badge">${item.healthy ? 'Ổn định' : 'Lỗi schema'}</span>
                            </div>
                            <p class="sqlm-health-summary">${escapeHtml(item.summary || '')}</p>
                            <div class="sqlm-health-issue">${escapeHtml(firstIssue)}</div>
                        </article>
                    `;
                }).join('')}
            </div>
        `;
    }

    function formatWarnings(warnings) {
        if (!Array.isArray(warnings) || warnings.length === 0) {
            return '';
        }

        return `
            <ul class="sqlm-report-list">
                ${warnings.map((warning) => `<li>${escapeHtml(warning)}</li>`).join('')}
            </ul>
        `;
    }

    function renderImportReportCard() {
        const report = state.importReport;
        if (!report || !report.result) {
            return '';
        }

        const result = report.result;
        const analysis = result.analysis || {};
        const execution = result.execution || {};
        const affectedModules = Array.isArray(analysis.affected_modules) ? analysis.affected_modules : [];
        const statusClass = report.success ? 'is-success' : (result.requires_confirmation ? 'is-warning' : 'is-danger');
        const title = report.success
            ? 'Kết quả import'
            : (result.requires_confirmation ? 'Cảnh báo preflight' : 'Import thất bại');
        const rollbackText = analysis.rollback_supported
            ? (execution.rollback_succeeded ? 'Đã rollback toàn bộ.' : 'Có thể rollback toàn bộ nếu lỗi trước commit.')
            : 'Không đảm bảo rollback đầy đủ do có DDL/mixed statements.';

        return `
            <section class="sqlm-shell-card sqlm-report-card ${statusClass}">
                <div class="sqlm-shell-card-head">
                    <h3 class="sqlm-panel-title">${title}</h3>
                    <p class="sqlm-panel-subtitle">${escapeHtml(report.message || result.message || '')}</p>
                </div>
                <div class="sqlm-report-meta">
                    <span class="sqlm-meta-pill">Statement: ${escapeHtml(String(analysis.statement_count || 0))}</span>
                    <span class="sqlm-meta-pill">Thành công: ${escapeHtml(String(execution.successful_statements || 0))}</span>
                    <span class="sqlm-meta-pill">Rollback: ${analysis.rollback_supported ? 'Có thể' : 'Giới hạn'}</span>
                </div>
                ${analysis.warnings && analysis.warnings.length ? `
                    <div class="sqlm-report-block">
                        <strong>Cảnh báo</strong>
                        ${formatWarnings(analysis.warnings)}
                    </div>
                ` : ''}
                ${result.requires_confirmation ? `
                    <div class="sqlm-report-block">
                        <strong>Preflight</strong>
                        <div class="sqlm-report-kv"><span>DDL statements</span><code>${escapeHtml(String(analysis.ddl_count || 0))}</code></div>
                        <div class="sqlm-report-kv"><span>DML statements</span><code>${escapeHtml(String(analysis.dml_count || 0))}</code></div>
                        <div class="sqlm-report-kv"><span>Rollback thực tế</span><span>${escapeHtml(rollbackText)}</span></div>
                        <div class="sqlm-report-kv"><span>Module bị tác động</span><span>${affectedModules.length ? escapeHtml(affectedModules.join(', ')) : 'Không xác định'}</span></div>
                    </div>
                ` : !report.success ? `
                    <div class="sqlm-report-block">
                        <strong>Lỗi</strong>
                        <div class="sqlm-report-kv"><span>Statement lỗi</span><code>#${escapeHtml(String(execution.failed_statement_number || '-'))}</code></div>
                        <div class="sqlm-report-kv"><span>Line gần đúng</span><code>${escapeHtml(String(execution.failed_line || '-'))}</code></div>
                        <div class="sqlm-report-kv"><span>SQLSTATE / code</span><code>${escapeHtml(String(execution.sqlstate || '-'))} / ${escapeHtml(String(execution.error_code || '-'))}</code></div>
                        <div class="sqlm-report-kv"><span>Rollback thực tế</span><span>${escapeHtml(rollbackText)}</span></div>
                        ${execution.database_message ? `<div class="sqlm-report-db-error">${escapeHtml(execution.database_message)}</div>` : ''}
                        ${execution.failed_sql_preview ? `<pre class="sqlm-report-sql">${escapeHtml(execution.failed_sql_preview)}</pre>` : ''}
                    </div>
                ` : `
                    <div class="sqlm-report-block">
                        <strong>Tổng kết</strong>
                        <div class="sqlm-report-kv"><span>Thời gian</span><code>${escapeHtml(String(execution.duration_ms || 0))} ms</code></div>
                        <div class="sqlm-report-kv"><span>Module bị tác động</span><span>${affectedModules.length ? escapeHtml(affectedModules.join(', ')) : 'Không xác định'}</span></div>
                    </div>
                `}
                <div class="sqlm-report-block">
                    <strong>Module health sau import</strong>
                    ${formatModuleHealthCards(result.module_health || state.healthSummary, affectedModules)}
                </div>
            </section>
        `;
    }

    /* RENDER */

    function updateStatusBar() {
        const context = state.context;
        const pagination = context && context.pagination ? context.pagination : { current_page: 1, last_page: 1, total: 0, offset: 0 };
        const row = selectedRow();
        const pending = transactionPendingCount();
        const isDataTab = state.activeTab === 'data';
        const isStructureTab = state.activeTab === 'structure';
        const isQueryTab = state.activeTab === 'query';
        const isSearchTab = state.activeTab === 'search';
        const isInsertTab = state.activeTab === 'insert';
        const isExportTab = state.activeTab === 'export';
        const isImportTab = state.activeTab === 'import';
        const isOperationsTab = state.activeTab === 'operations';

        let label = 'Ready';
        let summary = context ? `${pagination.total} bản ghi` : 'Chưa chọn bảng';
        let selection = row && isDataTab
            ? `Đang chọn dòng ${pagination.offset + state.selectedRowIndex + 1}`
            : 'Chưa chọn dòng';
        const rowActionsSupported = supportsRowMutations(context);

        if (isDataTab && context) {
            label = `Data · ${context.table}`;
            summary = `Trang ${pagination.current_page}/${pagination.last_page} · ${pagination.total} bản ghi`;
            if (hasActiveTransaction()) {
                selection = `Transaction: ${pending} thao tác đang stage`;
            } else if (!rowActionsSupported) {
                selection = rowActionMeta(context).reason || 'Bảng hiện tại không hỗ trợ sửa/xóa trực tiếp.';
            } else if (!row) {
                selection = 'Chọn một dòng để bật Sửa / Xóa';
            }
        } else if (isStructureTab && context) {
            label = `Structure · ${context.table}`;
            summary = `${context.columns.length} cột · ${context.overview.engine || '-'}`;
            selection = rowActionsSupported
                ? `${rowIdentityLabel(context)} · Collation: ${context.overview.collation || '-'}`
                : (rowActionMeta(context).reason || `Collation: ${context.overview.collation || '-'}`);
        } else if (isQueryTab) {
            if (state.queryResult) {
                label = `Query · ${(state.queryResult.query_kind || 'result').toUpperCase()}`;
                summary = `${state.queryResult.row_count || 0} dòng · ${state.queryResult.duration_ms || 0} ms`;
                selection = state.queryResult.truncated ? 'Kết quả đã cắt ở 200 dòng' : 'Read-only result set';
            } else if (state.queryError) {
                label = 'SQL Console · Error';
                summary = state.queryError.sqlstate
                    ? `SQLSTATE ${state.queryError.sqlstate}`
                    : 'Query failed';
                selection = state.queryError.message || 'Database trả về lỗi khi chạy query.';
            } else {
                label = 'SQL Console';
                summary = 'Chế độ read-only';
                selection = 'SELECT, SHOW, DESCRIBE, EXPLAIN';
            }
        } else if (isSearchTab && context) {
            label = `Search · ${context.table}`;
            summary = context.filters.search ? `Keyword: ${context.filters.search}` : 'Chưa áp keyword';
            selection = `Sort: ${context.filters.sort_column || '-'} ${String(context.filters.sort_direction || '').toUpperCase()}`;
        } else if (isInsertTab && context) {
            label = `Insert · ${context.table}`;
            summary = 'Form insert dùng backend hiện có';
            selection = hasActiveTransaction() ? `Transaction: ${pending} thao tác stage` : 'Mở modal để thêm dòng mới';
        } else if (isExportTab && context) {
            label = `Export · ${context.table}`;
            summary = `${pagination.total} bản ghi khả dụng ở bảng hiện tại`;
            selection = 'Hỗ trợ export CSV trang hiện tại và chart nhanh';
        } else if (isImportTab && context) {
            label = `Import · ${context.table}`;
            if (state.importReport && state.importReport.result) {
                const execution = state.importReport.result.execution || {};
                summary = state.importReport.success
                    ? `Import thành công · ${execution.successful_statements || 0} statement`
                    : `Import lỗi · statement #${execution.failed_statement_number || '-'}`;
                selection = state.importReport.result.analysis && state.importReport.result.analysis.rollback_supported
                    ? 'Rollback đầy đủ được hỗ trợ cho file DML-only.'
                    : 'DDL/mixed statements có thể không rollback đầy đủ.';
            } else {
                summary = 'Nhập SQL, CSV hoặc JSON';
                selection = hasActiveTransaction() ? `Transaction: ${pending} thao tác stage` : 'Import trực tiếp, có preflight với file SQL';
            }
        } else if (isOperationsTab && context) {
            label = `Operations · ${context.table}`;
            summary = hasActiveTransaction() ? `${pending} thao tác đang stage` : 'Transaction đang tắt';
            selection = 'Các thao tác bảng nâng cao chưa có backend được hiển thị dạng shell';
        }

        refs.statusLabel.textContent = label;
        refs.statusSummary.textContent = summary;
        refs.statusSelection.textContent = selection;

        const showPaging = isDataTab && !!context;
        refs.prevPageBtn.classList.toggle('d-none', !showPaging);
        refs.nextPageBtn.classList.toggle('d-none', !showPaging);

        if (showPaging) {
            refs.prevPageBtn.disabled = pagination.current_page <= 1 || hasActiveTransaction();
            refs.nextPageBtn.disabled = pagination.current_page >= pagination.last_page || hasActiveTransaction();
        }
    }

    function updateToolbarMeta() {
        const context = state.context;
        const pending = transactionPendingCount();
        const row = selectedRow();
        const currentMode = modeConfig(state.activeTab);

        refs.currentTable.textContent = context ? context.table : 'N/A';
        if (refs.headTable) {
            refs.headTable.textContent = context ? context.table : 'N/A';
        }
        if (refs.modeTitle) {
            refs.modeTitle.textContent = currentMode.title;
        }
        if (refs.modeDescription) {
            refs.modeDescription.textContent = currentMode.description;
        }
        if (refs.modeBadge) {
            refs.modeBadge.textContent = currentMode.badge;
        }
        if (refs.explorerState) {
            refs.explorerState.textContent = currentMode.title;
        }
        if (refs.tableCountPill) {
            refs.tableCountPill.innerHTML = `<i class="fas fa-table"></i>${state.tables.length} bảng`;
        }
        refs.primaryKeys.textContent = rowIdentityLabel(context);
        refs.rowCount.textContent = context
            ? `${context.pagination.total} bản ghi${pending > 0 ? ` · ${pending} pending` : ''}`
            : '0 bản ghi';
        refs.engine.textContent = context ? `Engine: ${context.overview.engine || '-'}` : 'Engine: -';
        refs.txnState.textContent = hasActiveTransaction() ? `Transaction: ${pending} staged` : 'Transaction: Off';
        refs.txnState.classList.toggle('sqlm-pill-active', hasActiveTransaction());
        const canMutateSelectedRow = !!row && supportsRowMutations(context) && !state.transaction.busy;
        refs.editBtn.disabled = !canMutateSelectedRow;
        refs.deleteBtn.disabled = !canMutateSelectedRow;
        refs.editBtn.title = supportsRowMutations(context)
            ? (row ? 'Sửa dòng đang chọn' : 'Chọn một dòng để sửa')
            : (rowActionMeta(context).reason || 'Bảng hiện tại không hỗ trợ sửa trực tiếp');
        refs.deleteBtn.title = supportsRowMutations(context)
            ? (row ? 'Xóa dòng đang chọn' : 'Chọn một dòng để xóa')
            : (rowActionMeta(context).reason || 'Bảng hiện tại không hỗ trợ xóa trực tiếp');
        refs.txnBtn.disabled = !context || hasActiveTransaction() || state.transaction.busy;
        refs.txnBtn.classList.toggle('d-none', hasActiveTransaction());
        refs.commitBtn.classList.toggle('d-none', !hasActiveTransaction());
        refs.rollbackBtn.classList.toggle('d-none', !hasActiveTransaction());
        refs.commitBtn.disabled = !hasActiveTransaction() || pending === 0 || state.transaction.busy;
        refs.rollbackBtn.disabled = !hasActiveTransaction() || state.transaction.busy;
        refs.insertBtn.disabled = !context || state.transaction.busy;
        refs.wrapBtn.classList.toggle('is-active', state.wrapText);
        app.classList.toggle('is-wrap-text', state.wrapText);
        app.classList.toggle('is-explorer-collapsed', state.explorerCollapsed);
        if (refs.explorerToggleBtn) {
            refs.explorerToggleBtn.classList.toggle('is-active', !state.explorerCollapsed);
            refs.explorerToggleBtn.title = state.explorerCollapsed ? 'Show Database Explorer' : 'Hide Database Explorer';
        }
        document.querySelectorAll('[data-sqlm-toolbar]').forEach((group) => {
            group.classList.toggle('is-active', group.getAttribute('data-sqlm-toolbar') === state.activeTab);
        });
        updateStatusBar();
    }

    async function openTableDetail(tableName, detail) {
        if (!tableName) {
            return;
        }

        state.structureFocus = detail || 'columns';
        state.expandedTables[tableName] = true;
        if (tableName !== state.selectedTable) {
            await loadTable(tableName, { page: 1 });
        } else {
            renderStructurePanel();
            renderTableList();
        }
        setActiveTab('structure');
    }

    function renderTableList() {
        const keyword = (refs.tableSearch.value || '').toLowerCase().trim();
        const visible = state.tables.filter((table) => {
            const name = String(table.name || '').toLowerCase();
            return keyword === '' || name.includes(keyword);
        });

        const shouldExpand = keyword !== '' ? true : state.databaseExpanded;
        app.classList.toggle('sqlm-db-collapsed', !shouldExpand);

        if (refs.databaseToggle) {
            refs.databaseToggle.classList.toggle('is-open', shouldExpand);
            refs.databaseToggle.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');
        }

        if (refs.databaseStatus) {
            refs.databaseStatus.textContent = keyword !== ''
                ? `${visible.length} bảng khớp bộ lọc`
                : `${state.tables.length} bảng trong schema hiện tại`;
        }

        if (visible.length === 0) {
            refs.tableList.innerHTML = '<div class="sqlm-empty m-3">Không tìm thấy bảng phù hợp.</div>';
            return;
        }

        refs.tableList.innerHTML = visible.map((table) => {
            const locked = hasActiveTransaction() && table.name !== state.transaction.table;
            const isActive = table.name === state.selectedTable;
            const expanded = keyword !== '' ? true : (state.expandedTables[table.name] !== undefined ? !!state.expandedTables[table.name] : isActive);
            const detailContext = isActive ? state.context : null;
            const columnCount = detailContext && Array.isArray(detailContext.columns) ? detailContext.columns.length : null;
            const indexCount = detailContext && Array.isArray(detailContext.indexes) ? detailContext.indexes.length : null;
            const foreignKeyCount = detailContext && Array.isArray(detailContext.foreign_keys) ? detailContext.foreign_keys.length : null;
            const triggerCount = detailContext && Array.isArray(detailContext.triggers) ? detailContext.triggers.length : null;
            const detailLabel = (label, count) => count === null ? label : `${label} (${count})`;
            return `
                <div class="sqlm-table-node ${isActive ? 'is-active' : ''} ${expanded ? 'is-expanded' : ''}">
                    <div class="sqlm-table-row">
                        <button type="button" class="sqlm-table-caret" data-table-toggle="${escapeHtml(table.name)}" aria-label="Toggle ${escapeHtml(table.name)}">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <button type="button" class="sqlm-table-item" data-table-name="${escapeHtml(table.name)}" ${locked ? 'disabled' : ''}>
                            <span class="sqlm-table-item-main">
                                <span class="sqlm-table-icon"><i class="fas fa-table"></i></span>
                                <span class="sqlm-table-text">
                                    <strong>${escapeHtml(table.name)}</strong>
                                    <small>${escapeHtml(table.engine || 'InnoDB')} · ${escapeHtml(table.collation || 'n/a')}</small>
                                </span>
                            </span>
                            <div class="sqlm-table-meta">
                                <span>${Number(table.estimated_rows || 0)} rows</span>
                                <span>AI ${table.auto_increment ? escapeHtml(String(table.auto_increment)) : '-'}</span>
                                ${locked ? '<span class="sqlm-table-lock">Locked by transaction</span>' : ''}
                            </div>
                        </button>
                    </div>
                    <div class="sqlm-table-children">
                        <button type="button" class="sqlm-table-child ${isActive && state.structureFocus === 'columns' ? 'is-active' : ''}" data-table-detail="columns" data-detail-table="${escapeHtml(table.name)}">
                            <i class="fas fa-list"></i><span>${escapeHtml(detailLabel('Columns', columnCount))}</span>
                        </button>
                        <button type="button" class="sqlm-table-child ${isActive && state.structureFocus === 'indexes' ? 'is-active' : ''}" data-table-detail="indexes" data-detail-table="${escapeHtml(table.name)}">
                            <i class="fas fa-key"></i><span>${escapeHtml(detailLabel('Indexes', indexCount))}</span>
                        </button>
                        <button type="button" class="sqlm-table-child ${isActive && state.structureFocus === 'foreign_keys' ? 'is-active' : ''}" data-table-detail="foreign_keys" data-detail-table="${escapeHtml(table.name)}">
                            <i class="fas fa-link"></i><span>${escapeHtml(detailLabel('Foreign keys', foreignKeyCount))}</span>
                        </button>
                        <button type="button" class="sqlm-table-child ${isActive && state.structureFocus === 'triggers' ? 'is-active' : ''}" data-table-detail="triggers" data-detail-table="${escapeHtml(table.name)}">
                            <i class="fas fa-bolt"></i><span>${escapeHtml(detailLabel('Triggers', triggerCount))}</span>
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        refs.tableList.querySelectorAll('[data-table-name]').forEach((button) => {
            button.addEventListener('click', () => {
                loadTable(button.getAttribute('data-table-name') || '', { page: 1 });
            });
        });

        refs.tableList.querySelectorAll('[data-table-toggle]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                const tableName = button.getAttribute('data-table-toggle') || '';
                state.expandedTables[tableName] = !(state.expandedTables[tableName] !== undefined ? state.expandedTables[tableName] : tableName === state.selectedTable);
                renderTableList();
            });
        });

        refs.tableList.querySelectorAll('[data-table-detail]').forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.stopPropagation();
                const tableName = button.getAttribute('data-detail-table') || '';
                const detail = button.getAttribute('data-table-detail') || 'columns';
                await openTableDetail(tableName, detail);
            });
        });
    }

    function syncFilterControls() {
        if (!state.context) {
            refs.searchInput.value = '';
            refs.perPage.value = '25';
            refs.sortColumn.innerHTML = '<option value="">-</option>';
            refs.sortDirection.value = 'asc';
            return;
        }

        refs.searchInput.value = state.context.filters.search || '';
        refs.perPage.value = String(state.context.pagination.per_page || 25);
        refs.sortDirection.value = state.context.filters.sort_direction || 'asc';
        refs.sortColumn.innerHTML = state.context.columns.map((column) => {
            const selected = column.name === state.context.filters.sort_column ? 'selected' : '';
            const label = `${column.name} (${column.column_type || column.data_type || ''})`;
            return `<option value="${escapeHtml(column.name)}" ${selected}>${escapeHtml(label)}</option>`;
        }).join('');
    }

    function renderDataPanel() {
        const context = state.context;
        if (!context) {
            refs.dataPanel.innerHTML = '<div class="sqlm-empty">Chưa có dữ liệu để hiển thị.</div>';
            return;
        }

        const rows = Array.isArray(context.rows) ? context.rows : [];
        const columns = Array.isArray(context.columns) ? context.columns : [];
        const pagination = context.pagination || {};
        const preview = resolvePreviewPayload();
        const rowActionsSupported = supportsRowMutations(context);
        const rowActionReason = rowActionMeta(context).reason || 'Bảng hiện tại không có row key hợp lệ để sửa/xóa trực tiếp.';

        refs.dataPanel.innerHTML = `
            <div class="sqlm-data-layout">
                <div class="sqlm-grid-card">
                    <div class="sqlm-grid-head">
                        <div>
                            <strong>Rows</strong>
                            <div class="small text-secondary">Trang ${pagination.current_page || 1}/${pagination.last_page || 1} · ${pagination.total || 0} bản ghi</div>
                        </div>
                        <div class="sqlm-grid-head-tools">
                            <span class="sqlm-inline-note">${rowActionsSupported ? 'Chọn một dòng để bật Sửa/Xóa, click ô dài để xem full ở panel bên phải' : rowActionReason}</span>
                            <button type="button" class="btn btn-sm btn-light border" data-sqlm-action="auto-fit-columns">Auto Fit</button>
                            <button type="button" class="btn btn-sm btn-light border" data-sqlm-action="reset-column-widths">Reset Widths</button>
                        </div>
                    </div>
                    ${!rowActionsSupported ? `<div class="sqlm-inline-warning">${escapeHtml(rowActionReason)}</div>` : ''}
                    <div class="sqlm-grid-wrap">
                        <table class="table admin-table sqlm-grid-table align-middle">
                            <thead>
                                <tr>
                                    <th class="sqlm-select-col" data-col-name="__select__" style="width:${resolveColumnWidth('__select__')}px;min-width:${resolveColumnWidth('__select__')}px;max-width:${resolveColumnWidth('__select__')}px">
                                        <span class="sqlm-th-label">Chọn</span>
                                        <span class="sqlm-col-resizer" data-col-resize="__select__" title="Kéo để đổi rộng, double click để auto-fit"></span>
                                    </th>
                                    <th data-col-name="__row_index__" style="width:${resolveColumnWidth('__row_index__')}px;min-width:${resolveColumnWidth('__row_index__')}px;max-width:${resolveColumnWidth('__row_index__')}px">
                                        <span class="sqlm-th-label">#</span>
                                        <span class="sqlm-col-resizer" data-col-resize="__row_index__" title="Kéo để đổi rộng, double click để auto-fit"></span>
                                    </th>
                                    ${columns.map((column) => {
                                        const width = columnWidth(column);
                                        return `<th data-col-name="${escapeHtml(column.name)}" style="width:${width}px;min-width:${width}px;max-width:${width}px">
                                            <span class="sqlm-th-label">${escapeHtml(column.name)}</span>
                                            <span class="sqlm-col-resizer" data-col-resize="${escapeHtml(column.name)}" title="Kéo để đổi rộng, double click để auto-fit"></span>
                                        </th>`;
                                    }).join('')}
                                    <th class="sqlm-action-col" data-col-name="__actions__" style="width:${resolveColumnWidth('__actions__')}px;min-width:${resolveColumnWidth('__actions__')}px;max-width:${resolveColumnWidth('__actions__')}px">
                                        <span class="sqlm-th-label">Actions</span>
                                        <span class="sqlm-col-resizer" data-col-resize="__actions__" title="Kéo để đổi rộng, double click để auto-fit"></span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rows.length > 0 ? rows.map((row, index) => `
                                    <tr class="sqlm-row ${state.selectedRowIndex === index ? 'is-selected' : ''} ${row.__sqlmPending ? 'is-pending' : ''}" data-row-index="${index}">
                                        <td class="sqlm-select-col" data-col-name="__select__" style="width:${resolveColumnWidth('__select__')}px;min-width:${resolveColumnWidth('__select__')}px;max-width:${resolveColumnWidth('__select__')}px">
                                            <button type="button" class="sqlm-row-pick ${state.selectedRowIndex === index ? 'is-selected' : ''}" data-row-pick="${index}" aria-label="Chọn dòng ${pagination.offset + index + 1}">
                                                <span class="sqlm-row-pick-dot"></span>
                                            </button>
                                        </td>
                                        <td data-col-name="__row_index__" style="width:${resolveColumnWidth('__row_index__')}px;min-width:${resolveColumnWidth('__row_index__')}px;max-width:${resolveColumnWidth('__row_index__')}px">
                                            <div class="sqlm-row-index">
                                                <span>${pagination.offset + index + 1}</span>
                                                ${row.__sqlmPending ? '<span class="sqlm-badge sqlm-badge-pending">Pending</span>' : ''}
                                            </div>
                                        </td>
                                        ${columns.map((column) => {
                                            const rawValue = row[column.name];
                                            const fullText = normalizePreviewValue(rawValue);
                                            const width = columnWidth(column);
                                            const isEditing = state.inlineEdit && state.inlineEdit.rowIndex === index && state.inlineEdit.column === column.name;
                                            const status = state.cellStatus[cellKey(index, column.name)] || {};
                                            return `
                                                <td class="${cellClass(index, column.name)}" data-cell="${index}:${escapeHtml(column.name)}" data-col-name="${escapeHtml(column.name)}" title="${escapeHtml(status.message || fullText)}" style="width:${width}px;min-width:${width}px;max-width:${width}px">
                                                    ${isEditing ? renderCellEditor(row, index, column) : renderCellDisplay(row, index, column)}
                                                </td>
                                            `;
                                        }).join('')}
                                        <td class="sqlm-row-actions" data-col-name="__actions__" style="width:${resolveColumnWidth('__actions__')}px;min-width:${resolveColumnWidth('__actions__')}px;max-width:${resolveColumnWidth('__actions__')}px">
                                            <button type="button" class="btn btn-sm btn-light border" data-row-edit="${index}" ${!rowActionsSupported ? 'disabled' : ''} title="${escapeHtml(rowActionsSupported ? 'Sửa dòng này' : rowActionReason)}"><i class="fas fa-pen"></i></button>
                                            <button type="button" class="btn btn-sm btn-light border border-danger text-danger" data-row-delete="${index}" ${!rowActionsSupported ? 'disabled' : ''} title="${escapeHtml(rowActionsSupported ? 'Xóa dòng này' : rowActionReason)}"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                `).join('') : `<tr><td colspan="${columns.length + 3}" class="text-center text-secondary py-4">Không có dữ liệu.</td></tr>`}
                            </tbody>
                        </table>
                    </div>
                </div>

                <aside class="sqlm-preview-card">
                    <div class="sqlm-preview-head">
                        <div>
                            <span class="sqlm-preview-kicker">Cell preview</span>
                            <strong>${escapeHtml(preview.rowLabel)}</strong>
                        </div>
                        <span class="sqlm-preview-column">${escapeHtml(preview.columnLabel)}</span>
                    </div>
                    <div class="sqlm-preview-body">
                        <pre>${escapeHtml(preview.value)}</pre>
                    </div>
                </aside>
            </div>
        `;

        refs.dataPanel.querySelectorAll('tr[data-row-index]').forEach((rowNode) => {
            rowNode.addEventListener('click', () => {
                setSelectedRow(Number(rowNode.getAttribute('data-row-index')));
                renderDataPanel();
                updateToolbarMeta();
            });
            rowNode.addEventListener('dblclick', () => {
                setSelectedRow(Number(rowNode.getAttribute('data-row-index')));
                renderDataPanel();
                updateToolbarMeta();
                openRowModal('update');
            });
        });

        refs.dataPanel.querySelectorAll('[data-row-pick]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                setSelectedRow(Number(button.getAttribute('data-row-pick')));
                renderDataPanel();
                updateToolbarMeta();
            });
        });

        refs.dataPanel.querySelectorAll('[data-cell-edit]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                const rowIndex = Number(button.getAttribute('data-row-index'));
                const columnName = button.getAttribute('data-column') || '';
                setSelectedRow(rowIndex, columnName);
                beginInlineEdit(rowIndex, columnName);
            });
            button.addEventListener('dblclick', (event) => {
                event.preventDefault();
                event.stopPropagation();
                beginInlineEdit(Number(button.getAttribute('data-row-index')), button.getAttribute('data-column') || '');
            });
            button.addEventListener('contextmenu', (event) => {
                event.stopPropagation();
                openCellContextMenu(event, Number(button.getAttribute('data-row-index')), button.getAttribute('data-column') || '');
            });
        });

        refs.dataPanel.querySelectorAll('[data-inline-editor]').forEach((editor) => {
            editor.addEventListener('click', (event) => event.stopPropagation());
            editor.addEventListener('pointerdown', (event) => event.stopPropagation());
            editor.addEventListener('input', () => autoResizeInlineEditor(editor));
            editor.addEventListener('blur', () => {
                window.setTimeout(() => {
                    if (state.inlineEdit && document.activeElement !== editor && !document.activeElement?.hasAttribute('data-format-json')) {
                        saveInlineEditor(editor);
                    }
                }, 80);
            });
            editor.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    cancelInlineEdit();
                    return;
                }
                if (event.key === 'Tab') {
                    event.preventDefault();
                    saveInlineEditor(editor, event.shiftKey ? -1 : 1);
                    return;
                }
                if (event.key === 'Enter') {
                    if (event.altKey && editor.tagName === 'TEXTAREA') {
                        event.preventDefault();
                        openLargeEditor(editor);
                        return;
                    }
                    const isTextarea = editor.tagName === 'TEXTAREA';
                    if (!isTextarea || event.ctrlKey) {
                        event.preventDefault();
                        saveInlineEditor(editor);
                    }
                }
            });
        });

        refs.dataPanel.querySelectorAll('[data-format-json]').forEach((button) => {
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => {
                const editor = refs.dataPanel.querySelector('[data-inline-editor]');
                if (!editor) return;
                try {
                    editor.value = JSON.stringify(JSON.parse(editor.value || '{}'), null, 2);
                    autoResizeInlineEditor(editor);
                    editor.focus();
                } catch (error) {
                    showFlash('JSON không hợp lệ nên chưa format được.', 'danger');
                }
            });
        });

        refs.dataPanel.querySelectorAll('[data-col-resize]').forEach((handle) => {
            const stopResizeEvent = (event) => {
                event.preventDefault();
                event.stopPropagation();
            };
            handle.addEventListener('mousedown', stopResizeEvent);
            handle.addEventListener('click', stopResizeEvent);
            handle.addEventListener('pointerdown', startColumnResize);
            handle.addEventListener('dblclick', (event) => {
                event.preventDefault();
                event.stopPropagation();
                autoFitColumn(handle.getAttribute('data-col-resize') || '');
            });
        });

        refs.dataPanel.querySelectorAll('[data-row-edit]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                setSelectedRow(Number(button.getAttribute('data-row-edit')));
                renderDataPanel();
                updateToolbarMeta();
                openRowModal('update');
            });
        });

        refs.dataPanel.querySelectorAll('[data-row-delete]').forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.stopPropagation();
                setSelectedRow(Number(button.getAttribute('data-row-delete')));
                renderDataPanel();
                updateToolbarMeta();
                await deleteSelectedRow();
            });
        });

        applyColumnWidths();
    }

    function renderStructurePanel() {
        const context = state.context;
        if (!context) {
            refs.structurePanel.innerHTML = '<div class="sqlm-empty">Chưa có schema để hiển thị.</div>';
            return;
        }

        const indexes = Array.isArray(context.indexes) ? context.indexes : [];
        const foreignKeys = Array.isArray(context.foreign_keys) ? context.foreign_keys : [];
        const triggers = Array.isArray(context.triggers) ? context.triggers : [];
        const focus = state.structureFocus || 'columns';
        const focusLabels = {
            columns: `Columns (${context.columns.length})`,
            indexes: `Indexes (${indexes.length})`,
            foreign_keys: `Foreign keys (${foreignKeys.length})`,
            triggers: `Triggers (${triggers.length})`,
        };
        const focusTitle = focusLabels[focus] || focusLabels.columns;
        const columnsTable = `
            <table class="table admin-table sqlm-grid-table align-middle">
                <thead>
                    <tr>
                        <th>Cột</th>
                        <th>Kiểu</th>
                        <th>Null</th>
                        <th>Key</th>
                        <th>Default</th>
                        <th>Extra</th>
                        <th>Comment</th>
                    </tr>
                </thead>
                <tbody>
                    ${context.columns.map((column) => `
                        <tr>
                            <td><strong>${escapeHtml(column.name)}</strong></td>
                            <td><code>${escapeHtml(column.column_type || column.data_type || '')}</code></td>
                            <td>${column.is_nullable ? '<span class="sqlm-badge">YES</span>' : '<span class="text-danger fw-semibold">NO</span>'}</td>
                            <td>${column.column_key ? `<span class="sqlm-badge">${escapeHtml(column.column_key)}</span>` : '-'}</td>
                            <td>${column.default_value === null ? '<span class="sqlm-null">NULL</span>' : `<code>${escapeHtml(String(column.default_value))}</code>`}</td>
                            <td>${column.extra ? `<code>${escapeHtml(column.extra)}</code>` : '-'}</td>
                            <td>${column.column_comment ? escapeHtml(column.column_comment) : '<span class="text-secondary">-</span>'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
        const indexesTable = indexes.length > 0 ? `
            <table class="table admin-table sqlm-grid-table align-middle">
                <thead><tr><th>Index</th><th>Columns</th><th>Unique</th><th>Type</th><th>Cardinality</th></tr></thead>
                <tbody>
                    ${indexes.map((index) => `
                        <tr>
                            <td><strong>${escapeHtml(index.name || '')}</strong></td>
                            <td>${(index.columns || []).map((column) => `<code>${escapeHtml(column.name || '')}</code>`).join(' ')}</td>
                            <td>${index.is_unique ? '<span class="sqlm-badge">YES</span>' : 'NO'}</td>
                            <td>${escapeHtml(index.type || '-')}</td>
                            <td>${escapeHtml(String(index.cardinality ?? '-'))}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        ` : '<div class="sqlm-empty">Bảng này chưa có index riêng ngoài metadata hệ thống.</div>';
        const foreignKeysTable = foreignKeys.length > 0 ? `
            <table class="table admin-table sqlm-grid-table align-middle">
                <thead><tr><th>Constraint</th><th>Column</th><th>References</th><th>On update</th><th>On delete</th></tr></thead>
                <tbody>
                    ${foreignKeys.map((key) => `
                        <tr>
                            <td><strong>${escapeHtml(key.constraint_name || '')}</strong></td>
                            <td><code>${escapeHtml(key.column_name || '')}</code></td>
                            <td><code>${escapeHtml(key.referenced_table || '')}.${escapeHtml(key.referenced_column || '')}</code></td>
                            <td>${escapeHtml(key.update_rule || '-')}</td>
                            <td>${escapeHtml(key.delete_rule || '-')}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        ` : '<div class="sqlm-empty">Không có foreign key trong bảng này.</div>';
        const triggersTable = triggers.length > 0 ? `
            <table class="table admin-table sqlm-grid-table align-middle">
                <thead><tr><th>Trigger</th><th>Timing</th><th>Event</th><th>Statement</th><th>Created</th></tr></thead>
                <tbody>
                    ${triggers.map((trigger) => `
                        <tr>
                            <td><strong>${escapeHtml(trigger.name || '')}</strong></td>
                            <td>${escapeHtml(trigger.timing || '-')}</td>
                            <td>${escapeHtml(trigger.event || '-')}</td>
                            <td><code>${escapeHtml(truncateCellText(trigger.statement || '', 160))}</code></td>
                            <td>${escapeHtml(trigger.created_at || '-')}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        ` : '<div class="sqlm-empty">Không có trigger trong bảng này.</div>';
        const detailContent = focus === 'indexes'
            ? indexesTable
            : (focus === 'foreign_keys' ? foreignKeysTable : (focus === 'triggers' ? triggersTable : columnsTable));

        refs.structurePanel.innerHTML = `
            <div class="sqlm-structure-grid">
                <div class="sqlm-mini-card">
                    <div class="sqlm-mini-head">Overview</div>
                    <div class="sqlm-mini-body">
                        <div class="sqlm-overview">
                            <div class="sqlm-overview-item"><span>Bảng</span><strong>${escapeHtml(context.table)}</strong></div>
                            <div class="sqlm-overview-item"><span>Engine</span><strong>${escapeHtml(context.overview.engine || '-')}</strong></div>
                            <div class="sqlm-overview-item"><span>Collation</span><strong>${escapeHtml(context.overview.collation || '-')}</strong></div>
                            <div class="sqlm-overview-item"><span>Row key</span><strong>${escapeHtml(rowIdentityLabel(context).replace(/^Primary key:\s|^Unique key:\s/, ''))}</strong></div>
                            <div class="sqlm-overview-item"><span>Rows</span><strong>${escapeHtml(String(context.pagination.total || 0))}</strong></div>
                            <div class="sqlm-overview-item"><span>Auto increment</span><strong>${escapeHtml(String(context.overview.auto_increment || '-'))}</strong></div>
                        </div>
                    </div>
                </div>
                <div class="sqlm-grid-card">
                    <div class="sqlm-grid-head">
                        <div>
                            <strong>${escapeHtml(focusTitle)}</strong>
                            <div class="small text-secondary">Metadata lấy từ information_schema của bảng đang chọn.</div>
                        </div>
                        <div class="sqlm-structure-tabs">
                            <button type="button" class="${focus === 'columns' ? 'is-active' : ''}" data-structure-focus="columns">Columns</button>
                            <button type="button" class="${focus === 'indexes' ? 'is-active' : ''}" data-structure-focus="indexes">Indexes</button>
                            <button type="button" class="${focus === 'foreign_keys' ? 'is-active' : ''}" data-structure-focus="foreign_keys">FKs</button>
                            <button type="button" class="${focus === 'triggers' ? 'is-active' : ''}" data-structure-focus="triggers">Triggers</button>
                        </div>
                    </div>
                    <div class="sqlm-grid-wrap">
                        ${detailContent}
                    </div>
                </div>
            </div>
        `;

        refs.structurePanel.querySelectorAll('[data-structure-focus]').forEach((button) => {
            button.addEventListener('click', () => {
                state.structureFocus = button.getAttribute('data-structure-focus') || 'columns';
                renderStructurePanel();
                renderTableList();
            });
        });

        applyStandaloneGridWidths(refs.structurePanel);
    }

    function renderQueryPanel() {
        if (state.queryError) {
            refs.queryPanel.innerHTML = `
                <div class="sqlm-shell-card sqlm-report-card is-danger">
                    <div class="sqlm-shell-card-head">
                        <h3 class="sqlm-panel-title">Query thất bại</h3>
                        <p class="sqlm-panel-subtitle">${escapeHtml(state.queryError.message || 'Truy vấn thất bại.')}</p>
                    </div>
                    <div class="sqlm-report-block">
                        <div class="sqlm-report-kv"><span>SQLSTATE / code</span><code>${escapeHtml(String(state.queryError.sqlstate || '-'))} / ${escapeHtml(String(state.queryError.error_code || '-'))}</code></div>
                        ${state.queryError.query_preview ? `<pre class="sqlm-report-sql">${escapeHtml(state.queryError.query_preview)}</pre>` : ''}
                    </div>
                </div>
            `;
            return;
        }

        if (!state.queryResult) {
            refs.queryPanel.innerHTML = '<div class="sqlm-empty">Chưa có kết quả query.</div>';
            return;
        }

        const result = state.queryResult;
        const columns = Array.isArray(result.columns) ? result.columns : [];
        const rows = Array.isArray(result.rows) ? result.rows : [];

        refs.queryPanel.innerHTML = `
            <div class="sqlm-grid-card">
                <div class="sqlm-grid-head">
                    <div>
                        <strong>Kết quả truy vấn</strong>
                        <div class="small text-secondary">${escapeHtml((result.query_kind || '').toUpperCase())} · ${escapeHtml(String(result.duration_ms || 0))} ms · ${escapeHtml(String(result.row_count || 0))} dòng${result.truncated ? ' · Đã cắt ở 200 dòng' : ''}</div>
                    </div>
                </div>
                <div class="sqlm-grid-wrap">
                    <table class="table admin-table sqlm-grid-table align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                ${columns.map((column) => `<th>${escapeHtml(column.name || '')}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.length > 0 ? rows.map((row, index) => `
                                <tr>
                                    <td>${index + 1}</td>
                                    ${columns.map((column) => `<td class="sqlm-cell">${cellHtml(row[column.name])}</td>`).join('')}
                                </tr>
                            `).join('') : `<tr><td colspan="${columns.length + 1}" class="text-center text-secondary py-4">Không có dữ liệu trả về.</td></tr>`}
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        applyStandaloneGridWidths(refs.queryPanel);
    }

    function renderSearchPanel() {
        const context = state.context;
        if (!context) {
            refs.searchPanel.innerHTML = '<div class="sqlm-empty">Chọn bảng trước khi dùng mode tìm kiếm.</div>';
            return;
        }

        refs.searchPanel.innerHTML = `
            <div class="sqlm-mode-shell-grid">
                <section class="sqlm-shell-card">
                    <div class="sqlm-shell-card-head">
                        <h3 class="sqlm-panel-title">Search in table</h3>
                        <p class="sqlm-panel-subtitle">Dùng bộ lọc backend hiện tại. Kết quả vẫn đổ về grid dữ liệu của bảng đang mở.</p>
                    </div>
                    <div class="sqlm-shell-actions">
                        <button type="button" class="btn btn-primary" data-sqlm-action="focus-filter">Mở filter</button>
                        <button type="button" class="btn btn-light border" data-sqlm-action="apply-filter">Áp dụng ngay</button>
                    </div>
                    <div class="sqlm-shell-note">Keyword hiện tại: <strong>${escapeHtml(context.filters.search || 'chưa có')}</strong></div>
                </section>
                <section class="sqlm-shell-card">
                    <div class="sqlm-shell-card-head">
                        <h3 class="sqlm-panel-title">Searchable columns</h3>
                        <p class="sqlm-panel-subtitle">Những cột đang được backend scan theo điều kiện LIKE.</p>
                    </div>
                    <div class="sqlm-shell-tags">
                        ${context.columns.map((column) => `<span class="sqlm-shell-tag">${escapeHtml(column.name)}</span>`).join('')}
                    </div>
                </section>
            </div>
        `;
    }

    function renderInsertPanel() {
        const context = state.context;
        if (!context) {
            refs.insertPanel.innerHTML = '<div class="sqlm-empty">Chọn bảng trước khi chèn dữ liệu.</div>';
            return;
        }

        refs.insertPanel.innerHTML = `
            <div class="sqlm-mode-shell-grid">
                <section class="sqlm-shell-card">
                    <div class="sqlm-shell-card-head">
                        <h3 class="sqlm-panel-title">Insert row</h3>
                        <p class="sqlm-panel-subtitle">Dùng form modal hiện có để thêm bản ghi mới an toàn theo schema bảng.</p>
                    </div>
                    <div class="sqlm-shell-actions">
                        <button type="button" class="btn btn-primary" data-sqlm-action="insert">Thêm dòng thủ công</button>
                        <button type="button" class="btn btn-light border" data-sqlm-action="generate">Sinh dữ liệu mẫu</button>
                    </div>
                </section>
                <section class="sqlm-shell-card">
                    <div class="sqlm-shell-card-head">
                        <h3 class="sqlm-panel-title">Cột sẽ xuất hiện trong form</h3>
                        <p class="sqlm-panel-subtitle">Preview nhanh các cột đầu tiên để thao tác insert bớt mù.</p>
                    </div>
                    <div class="sqlm-shell-tags">
                        ${context.columns.slice(0, 14).map((column) => `<span class="sqlm-shell-tag">${escapeHtml(column.name)}</span>`).join('')}
                    </div>
                </section>
            </div>
        `;
    }

    function renderExportPanel() {
        const context = state.context;
        const selectedTable = context ? context.table : state.selectedTable;
        const totalRows = context && context.pagination ? Number(context.pagination.total || 0) : 0;

        refs.exportPanel.innerHTML = `
            <div class="sqlm-mode-shell-grid">
                <section class="sqlm-shell-card">
                    <div class="sqlm-shell-card-head">
                        <h3 class="sqlm-panel-title">Export current page</h3>
                        <p class="sqlm-panel-subtitle">CSV nhanh cho dataset đang hiển thị trên grid.</p>
                    </div>
                    <div class="sqlm-shell-actions">
                        <button type="button" class="btn btn-primary" data-sqlm-action="export" ${context ? '' : 'disabled'}>Export CSV</button>
                        <button type="button" class="btn btn-light border" data-sqlm-action="chart">Create chart</button>
                    </div>
                    <div class="sqlm-shell-note">${escapeHtml(String(totalRows))} bản ghi trong bảng hiện tại. CSV chỉ export trang đang xem.</div>
                </section>
                <section class="sqlm-shell-card">
                    <div class="sqlm-shell-card-head">
                        <h3 class="sqlm-panel-title">SQL dump cho phpMyAdmin</h3>
                        <p class="sqlm-panel-subtitle">File .sql có DROP TABLE IF EXISTS, CREATE TABLE, INSERT data và FOREIGN_KEY_CHECKS=0.</p>
                    </div>
                    <div class="sqlm-shell-actions">
                        <button type="button" class="btn btn-primary" data-sqlm-action="export-sql-full">Full database .sql</button>
                        <button type="button" class="btn btn-light border" data-sqlm-action="export-sql-table" ${selectedTable ? '' : 'disabled'}>Current table .sql</button>
                        <button type="button" class="btn btn-light border" data-sqlm-action="export-sql-schema">Schema only</button>
                        <button type="button" class="btn btn-light border" data-sqlm-action="export-sql-data">Data only</button>
                    </div>
                    <div class="sqlm-shell-note">Dùng "Full database .sql" khi import lên phpMyAdmin để tránh lỗi #1050 table already exists. File này sẽ DROP bảng trùng tên trong database đích trước khi tạo lại. Data only dùng REPLACE INTO để giảm lỗi duplicate key.</div>
                </section>
            </div>
        `;
    }

    function renderImportPanel() {
        const context = state.context;
        const unhealthyModules = Array.isArray(state.healthSummary.unhealthy_modules) ? state.healthSummary.unhealthy_modules : [];
        refs.importPanel.innerHTML = `
            <div class="sqlm-mode-shell-grid">
                <section class="sqlm-shell-card">
                    <div class="sqlm-shell-card-head">
                        <h3 class="sqlm-panel-title">Import file</h3>
                        <p class="sqlm-panel-subtitle">CSV/JSON vẫn import theo tên cột. File SQL sẽ đi qua preflight, báo statement lỗi, line gần đúng và khả năng rollback.</p>
                    </div>
                    <div class="sqlm-shell-actions">
                        <button type="button" class="btn btn-primary" data-sqlm-action="import">Chọn file để import</button>
                    </div>
                    <div class="sqlm-shell-note">File .sql ưu tiên an toàn kiểu phpMyAdmin: không fake success, không silent fail, và cảnh báo rõ nếu MySQL có thể auto-commit DDL.</div>
                </section>
                <section class="sqlm-shell-card">
                    <div class="sqlm-shell-card-head">
                        <h3 class="sqlm-panel-title">Schema mapping</h3>
                        <p class="sqlm-panel-subtitle">${context ? 'Các cột backend hiện chấp nhận cho bảng này.' : 'CSV/JSON cần bảng đang chọn. Với file SQL, bạn có thể import ngay cả khi database đang rỗng.'}</p>
                    </div>
                    ${context ? `
                        <div class="sqlm-shell-tags">
                            ${context.columns.slice(0, 20).map((column) => `<span class="sqlm-shell-tag">${escapeHtml(column.name)}</span>`).join('')}
                        </div>
                    ` : `<div class="sqlm-shell-note">Chưa có bảng active. Chọn bảng nếu bạn muốn import CSV/JSON theo cột. File SQL không bị giới hạn bởi trạng thái này.</div>`}
                </section>
                <section class="sqlm-shell-card">
                    <div class="sqlm-shell-card-head">
                        <h3 class="sqlm-panel-title">Module health</h3>
                        <p class="sqlm-panel-subtitle">Module unhealthy sẽ bị chặn read/write ở controller liên quan thay vì làm hỏng toàn site.</p>
                    </div>
                    ${formatModuleHealthCards(state.healthSummary)}
                    <div class="sqlm-shell-note">${unhealthyModules.length > 0 ? `Hiện có module degraded: ${escapeHtml(unhealthyModules.join(', '))}` : 'Tất cả module theo dõi đang healthy.'}</div>
                </section>
                ${renderImportReportCard()}
            </div>
        `;
    }

    function renderOperationsPanel() {
        const context = state.context;
        if (!context) {
            refs.operationsPanel.innerHTML = '<div class="sqlm-empty">Chọn bảng trước khi dùng thao tác quản trị.</div>';
            return;
        }

        refs.operationsPanel.innerHTML = `
            <div class="sqlm-mode-shell-grid">
                <section class="sqlm-shell-card">
                    <div class="sqlm-shell-card-head">
                        <h3 class="sqlm-panel-title">Transaction control</h3>
                        <p class="sqlm-panel-subtitle">Flow commit/rollback đang là phần thao tác bảng có backend thật.</p>
                    </div>
                    <div class="sqlm-shell-actions">
                        <button type="button" class="btn btn-light border" data-sqlm-action="begin-txn" ${hasActiveTransaction() ? 'disabled' : ''}>Begin</button>
                        <button type="button" class="btn btn-success" data-sqlm-action="commit" ${!hasActiveTransaction() ? 'disabled' : ''}>Commit</button>
                        <button type="button" class="btn btn-outline-danger" data-sqlm-action="rollback" ${!hasActiveTransaction() ? 'disabled' : ''}>Rollback</button>
                    </div>
                    <div class="sqlm-shell-note">${hasActiveTransaction() ? `${transactionPendingCount()} thao tác đang stage.` : 'Chưa có transaction mở.'}</div>
                </section>
                <section class="sqlm-shell-card is-disabled">
                    <div class="sqlm-shell-card-head">
                        <h3 class="sqlm-panel-title">Table operations</h3>
                        <p class="sqlm-panel-subtitle">Các action kiểu phpMyAdmin như optimize/truncate/rename chưa có backend, nên chỉ hiển thị shell disabled.</p>
                    </div>
                    <div class="sqlm-shell-actions">
                        <button type="button" class="btn btn-light border" disabled>Optimize table</button>
                        <button type="button" class="btn btn-light border" disabled>Rename table</button>
                        <button type="button" class="btn btn-light border" disabled>Truncate table</button>
                    </div>
                </section>
            </div>
        `;
    }

    function renderAll() {
        renderTableList();
        syncFilterControls();
        renderDataPanel();
        renderStructurePanel();
        renderQueryPanel();
        renderSearchPanel();
        renderInsertPanel();
        renderExportPanel();
        renderImportPanel();
        renderOperationsPanel();
        updateToolbarMeta();
    }

    function setDefaultQuery() {
        if (refs.queryInput && state.selectedTable) {
            refs.queryInput.value = `SELECT * FROM \`${state.selectedTable}\` LIMIT 100`;
        }
    }

    /* ACTIONS */

    async function loadTable(tableName, overrides = {}) {
        if (!tableName) {
            return;
        }

        if (hasActiveTransaction() && !overrides.allowDuringTransaction) {
            showFlash('Transaction đang mở. Hãy commit hoặc rollback trước khi tải lại dữ liệu.', 'warning');
            return;
        }

        try {
            const current = state.context || { filters: {}, pagination: { per_page: 25 } };
            const params = new URLSearchParams({
                table: tableName,
                page: String(overrides.page || 1),
                per_page: String(overrides.per_page || refs.perPage.value || current.pagination.per_page || 25),
                search: overrides.search !== undefined ? overrides.search : (refs.searchInput.value || current.filters.search || ''),
                sort_column: overrides.sort_column !== undefined ? overrides.sort_column : (refs.sortColumn.value || current.filters.sort_column || ''),
                sort_direction: overrides.sort_direction !== undefined ? overrides.sort_direction : (refs.sortDirection.value || current.filters.sort_direction || 'asc'),
            });

            const payload = await requestJson(`${endpoints.tableData}?${params.toString()}`);
            state.context = decorateContext(payload.context || null);
            state.selectedTable = state.context ? state.context.table : tableName;
            state.expandedTables[state.selectedTable] = true;
            loadColumnWidths();
            state.selectedRowIndex = null;
            state.cellPreview = {
                rowIndex: null,
                column: '',
                value: '',
            };

            if (refs.queryInput && !refs.queryInput.value.trim()) {
                setDefaultQuery();
            }

            renderAll();
        } catch (error) {
            showFlash(error.message, 'danger');
        }
    }

    function queueOperation(operation) {
        state.transaction.queue.push({
            id: operation.id || createOperationId(),
            type: operation.type,
            table: operation.table,
            row_key: operation.row_key ? deepClone(operation.row_key) : undefined,
            values: operation.values ? deepClone(operation.values) : undefined,
        });
    }

    function startTransaction() {
        if (!state.context || hasActiveTransaction()) {
            return;
        }

        state.transaction.active = true;
        state.transaction.table = state.selectedTable;
        state.transaction.snapshot = deepClone(state.context);
        state.transaction.queue = [];
        state.transaction.busy = false;
        renderAll();
        showFlash('Transaction mode đã bật. Các thay đổi sẽ được stage cục bộ cho tới khi bạn commit.', 'info');
    }

    function endTransaction() {
        state.transaction.active = false;
        state.transaction.table = '';
        state.transaction.snapshot = null;
        state.transaction.queue = [];
        state.transaction.busy = false;
    }

    function rollbackTransaction() {
        if (!hasActiveTransaction() || state.transaction.busy) {
            return;
        }

        if (state.transaction.snapshot) {
            state.context = decorateContext(deepClone(state.transaction.snapshot));
        }
        state.selectedRowIndex = null;
        endTransaction();
        renderAll();
        showFlash('Đã rollback toàn bộ thay đổi đang stage.', 'warning');
    }

    async function commitTransaction() {
        if (!hasActiveTransaction() || state.transaction.busy) {
            return;
        }

        const operations = state.transaction.queue.map(normalizeOperationPayload);
        if (operations.length === 0) {
            endTransaction();
            renderAll();
            showFlash('Không có thao tác nào để commit.', 'info');
            return;
        }

        const currentPage = state.context && state.context.pagination ? state.context.pagination.current_page || 1 : 1;
        const params = new URLSearchParams();
        params.set('_csrf', state.csrfToken);
        params.set('operations_json', JSON.stringify(operations));

        try {
            state.transaction.busy = true;
            updateToolbarMeta();
            const payload = await requestJson(endpoints.commit, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-TOKEN': state.csrfToken,
                },
                body: params.toString(),
            });
            endTransaction();
            showFlash(payload.message || 'Đã commit transaction.', 'success');
            await loadTable(state.selectedTable, {
                page: currentPage,
                allowDuringTransaction: true,
            });
        } catch (error) {
            state.transaction.busy = false;
            updateToolbarMeta();
            showFlash(error.message, 'danger');
        }
    }

    function applyLocalInsert(values, operationId) {
        if (!state.context) {
            return;
        }

        state.context.rows.unshift(buildPreviewRow(values, operationId));
        state.context.pagination.total = Number(state.context.pagination.total || 0) + 1;
        state.selectedRowIndex = 0;
    }

    function applyLocalUpdate(rowKey, values) {
        const rowIndex = findRowIndexByKey(rowKey);
        if (rowIndex < 0) {
            return;
        }

        const row = state.context.rows[rowIndex];
        state.context.columns.forEach((column) => {
            if (Object.prototype.hasOwnProperty.call(values, column.name)) {
                row[column.name] = normalizeLocalValue(values[column.name]);
            }
        });
        row.__sqlmPending = true;
        state.selectedRowIndex = rowIndex;
    }

    function applyLocalDelete(rowKey) {
        const rowIndex = findRowIndexByKey(rowKey);
        if (rowIndex < 0) {
            return;
        }

        state.context.rows.splice(rowIndex, 1);
        state.context.pagination.total = Math.max(0, Number(state.context.pagination.total || 0) - 1);
        state.selectedRowIndex = null;
    }

    function updateQueuedInsert(values) {
        const operation = selectedPendingInsertOperation();
        const row = selectedRow();

        if (!operation || !row) {
            showFlash('Không tìm thấy bản ghi insert đang stage để cập nhật.', 'warning');
            return;
        }

        operation.values = deepClone(values);
        state.context.columns.forEach((column) => {
            if (Object.prototype.hasOwnProperty.call(values, column.name)) {
                row[column.name] = normalizeLocalValue(values[column.name]);
            }
        });
        row.__sqlmPending = true;
        rowModal.hide();
        renderDataPanel();
        updateToolbarMeta();
        showFlash('Đã cập nhật bản ghi insert đang stage.', 'info');
    }

    function openRowModal(mode, seedValues = null) {
        if (!state.context || !rowModal) {
            return;
        }

        if (mode === 'update' && !supportsRowMutations(state.context)) {
            showFlash(rowActionMeta(state.context).reason || 'Bảng hiện tại không hỗ trợ sửa trực tiếp.', 'warning');
            return;
        }

        const pendingInsert = mode === 'update' ? selectedPendingInsertOperation() : null;
        const row = mode === 'update' ? selectedRow() : null;
        if (mode === 'update' && !row) {
            showFlash('Hãy chọn một dòng trước khi sửa.', 'warning');
            return;
        }

        state.modalMode = pendingInsert ? 'transaction-insert-edit' : mode;
        refs.rowModalTitle.textContent = state.modalMode === 'insert'
            ? `Thêm bản ghi vào ${state.context.table}`
            : (state.modalMode === 'transaction-insert-edit'
                ? `Chỉnh sửa bản ghi đang stage của ${state.context.table}`
                : `Chỉnh sửa bản ghi của ${state.context.table}`);
        refs.rowModalSubtitle.textContent = state.modalMode === 'insert'
            ? 'Nhập dữ liệu cho dòng mới. Cột auto increment có thể để trống.'
            : (state.modalMode === 'transaction-insert-edit'
                ? 'Bản ghi này chưa được ghi xuống database. Lưu sẽ cập nhật bản stage hiện tại.'
                : 'Cập nhật dữ liệu từng cột của dòng đang chọn.');

        refs.rowForm.innerHTML = state.context.columns.map((column) => {
            const inputId = `sqlm-field-${column.name}`;
            const generated = isGeneratedColumn(column);
            const rawSeed = seedValues && Object.prototype.hasOwnProperty.call(seedValues, column.name)
                ? seedValues[column.name]
                : undefined;
            const rawValue = row && row[column.name] !== null && row[column.name] !== undefined
                ? String(row[column.name])
                : (rawSeed !== undefined && rawSeed !== null
                    ? String(rawSeed)
                    : (column.default_value !== null && column.default_value !== undefined ? String(column.default_value) : ''));
            const isNull = row ? row[column.name] === null : rawSeed === null;

            return `
                <div class="sqlm-form-card ${isMultilineColumn(column) ? 'is-wide' : ''}">
                    <div class="sqlm-form-head">
                        <div>
                            <strong>${escapeHtml(column.name)}</strong>
                            <div class="sqlm-form-meta">${escapeHtml(column.column_type || column.data_type || '')}</div>
                        </div>
                        <div class="d-flex gap-1 flex-wrap">
                            ${column.column_key ? `<span class="sqlm-badge">${escapeHtml(column.column_key)}</span>` : ''}
                            ${column.is_nullable ? '<span class="sqlm-badge">NULL</span>' : '<span class="sqlm-badge">NOT NULL</span>'}
                        </div>
                    </div>
                    ${isMultilineColumn(column)
                        ? `<textarea id="${escapeHtml(inputId)}" class="form-control" data-field-name="${escapeHtml(column.name)}" ${generated ? 'disabled' : ''}>${escapeHtml(rawValue)}</textarea>`
                        : `<input id="${escapeHtml(inputId)}" class="form-control" data-field-name="${escapeHtml(column.name)}" value="${escapeHtml(rawValue)}" ${generated ? 'disabled' : ''}>`
                    }
                    ${column.is_nullable ? `
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" value="1" id="${escapeHtml(inputId)}-null" data-null-toggle="${escapeHtml(column.name)}" ${isNull ? 'checked' : ''} ${generated ? 'disabled' : ''}>
                            <label class="form-check-label" for="${escapeHtml(inputId)}-null">Đặt NULL</label>
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');

        refs.rowForm.querySelectorAll('[data-null-toggle]').forEach((checkbox) => {
            const fieldName = checkbox.getAttribute('data-null-toggle');
            const input = refs.rowForm.querySelector(`[data-field-name="${selectorEscape(fieldName)}"]`);
            const sync = () => {
                if (input) {
                    const column = state.context.columns.find((item) => item.name === fieldName) || {};
                    input.disabled = checkbox.checked || isGeneratedColumn(column);
                }
            };
            checkbox.addEventListener('change', sync);
            sync();
        });

        rowModal.show();
    }

    function collectRowValues() {
        const values = {};
        refs.rowForm.querySelectorAll('[data-field-name]').forEach((field) => {
            const name = field.getAttribute('data-field-name');
            const nullToggle = refs.rowForm.querySelector(`[data-null-toggle="${selectorEscape(name)}"]`);
            values[name] = {
                value: field.value,
                is_null: !!(nullToggle && nullToggle.checked),
            };
        });
        return values;
    }

    async function saveRowForm() {
        if (!state.context) {
            return;
        }

        const values = collectRowValues();

        if (hasActiveTransaction()) {
            try {
                refs.saveRowBtn.disabled = true;

                if (state.modalMode === 'insert') {
                    const operationId = createOperationId();
                    queueOperation({ id: operationId, type: 'insert', table: state.context.table, values });
                    applyLocalInsert(values, operationId);
                    rowModal.hide();
                    renderDataPanel();
                    updateToolbarMeta();
                    showFlash('Đã stage bản ghi mới. Bấm Commit để ghi xuống database.', 'info');
                    return;
                }

                if (state.modalMode === 'transaction-insert-edit') {
                    updateQueuedInsert(values);
                    return;
                }

                const rowKey = selectedRowKey();
                if (!rowKey) {
                    showFlash('Không xác định được khóa chính của dòng cần sửa.', 'danger');
                    return;
                }

                queueOperation({ type: 'update', table: state.context.table, row_key: rowKey, values });
                applyLocalUpdate(rowKey, values);
                rowModal.hide();
                renderDataPanel();
                updateToolbarMeta();
                showFlash('Đã stage thay đổi cho dòng đang chọn. Bấm Commit để áp dụng.', 'info');
                return;
            } finally {
                refs.saveRowBtn.disabled = false;
            }
        }

        const params = new URLSearchParams();
        params.set('_csrf', state.csrfToken);
        params.set('table', state.context.table);
        params.set('values_json', JSON.stringify(values));

        let url = endpoints.insert;
        if (state.modalMode !== 'insert') {
            const rowKey = selectedRowKey();
            if (!rowKey) {
                showFlash('Không xác định được khóa chính của dòng cần sửa.', 'danger');
                return;
            }
            params.set('row_key_json', JSON.stringify(rowKey));
            url = endpoints.update;
        }

        try {
            refs.saveRowBtn.disabled = true;
            const payload = await requestJson(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-TOKEN': state.csrfToken,
                },
                body: params.toString(),
            });
            rowModal.hide();
            showFlash(payload.message || 'Đã lưu dữ liệu.', 'success');
            await loadTable(state.selectedTable, {
                page: state.context.pagination.current_page || 1,
                allowDuringTransaction: true,
            });
        } catch (error) {
            showFlash(error.message, 'danger');
        } finally {
            refs.saveRowBtn.disabled = false;
        }
    }

    async function deleteSelectedRow() {
        const row = selectedRow();
        if (!state.context || !row) {
            showFlash('Hãy chọn một dòng trước khi xóa.', 'warning');
            return;
        }

        if (!supportsRowMutations(state.context)) {
            showFlash(rowActionMeta(state.context).reason || 'Bảng hiện tại không hỗ trợ xóa trực tiếp.', 'warning');
            return;
        }

        if (!window.confirm(`Xóa dòng đang chọn khỏi bảng ${state.context.table}?`)) {
            return;
        }

        if (hasActiveTransaction()) {
            const pendingInsert = selectedPendingInsertOperation();
            if (pendingInsert) {
                state.transaction.queue = state.transaction.queue.filter((operation) => operation.id !== pendingInsert.id);
                state.context.rows.splice(state.selectedRowIndex, 1);
                state.context.pagination.total = Math.max(0, Number(state.context.pagination.total || 0) - 1);
                state.selectedRowIndex = null;
                renderDataPanel();
                updateToolbarMeta();
                showFlash('Đã bỏ bản ghi insert đang stage.', 'warning');
                return;
            }

            const rowKey = selectedRowKey();
            if (!rowKey) {
                showFlash('Không xác định được khóa chính của dòng cần xóa.', 'danger');
                return;
            }

            queueOperation({ type: 'delete', table: state.context.table, row_key: rowKey });
            applyLocalDelete(rowKey);
            renderDataPanel();
            updateToolbarMeta();
            showFlash('Đã stage thao tác xóa. Bấm Commit để áp dụng.', 'info');
            return;
        }

        const rowKey = selectedRowKey();
        if (!rowKey) {
            showFlash('Không xác định được khóa chính của dòng cần xóa.', 'danger');
            return;
        }

        const params = new URLSearchParams();
        params.set('_csrf', state.csrfToken);
        params.set('table', state.context.table);
        params.set('row_key_json', JSON.stringify(rowKey));

        try {
            const payload = await requestJson(endpoints.delete, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-TOKEN': state.csrfToken,
                },
                body: params.toString(),
            });
            showFlash(payload.message || 'Đã xóa dòng.', 'success');
            await loadTable(state.selectedTable, {
                page: state.context.pagination.current_page || 1,
                allowDuringTransaction: true,
            });
        } catch (error) {
            showFlash(error.message, 'danger');
        }
    }

    async function runQuery() {
        const sql = (refs.queryInput.value || '').trim();
        if (!sql) {
            showFlash('Bạn chưa nhập query.', 'warning');
            return;
        }

        const params = new URLSearchParams();
        params.set('_csrf', state.csrfToken);
        params.set('sql', sql);

        try {
            const payload = await requestJson(endpoints.query, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-TOKEN': state.csrfToken,
                },
                body: params.toString(),
            });
            state.queryResult = payload.result || null;
            state.queryError = null;
            renderQueryPanel();
            showFlash(payload.message || 'Chạy query thành công.', 'success');
            setActiveTab('query');
        } catch (error) {
            const rawMessage = String(error.message || 'Chạy query thất bại.');
            const parts = rawMessage.split(' | SQLSTATE: ');
            const mainMessage = parts[0] || rawMessage;
            let sqlState = '';
            let errorCode = '';

            if (parts[1]) {
                const sqlParts = parts[1].split(' | Code: ');
                sqlState = sqlParts[0] || '';
                errorCode = sqlParts[1] || '';
            }

            state.queryResult = null;
            state.queryError = {
                message: mainMessage,
                sqlstate: sqlState,
                error_code: errorCode,
                query_preview: sql,
            };
            renderQueryPanel();
            showFlash(error.message, 'danger');
            setActiveTab('query');
        }
    }

    function setActiveTab(tabName) {
        state.activeTab = tabName;
        document.querySelectorAll('[data-sqlm-tab]').forEach((tab) => {
            tab.classList.toggle('is-active', tab.getAttribute('data-sqlm-tab') === tabName);
        });
        document.querySelectorAll('.sqlm-mode-tab').forEach((tab) => {
            tab.classList.toggle('is-active', tab.getAttribute('data-sqlm-tab') === tabName);
        });
        document.querySelectorAll('[data-sqlm-panel]').forEach((panel) => {
            panel.classList.toggle('d-none', panel.getAttribute('data-sqlm-panel') !== tabName);
        });
        refs.filterRow.classList.toggle('d-none', !['data', 'search'].includes(tabName));
        app.setAttribute('data-sqlm-active-tab', tabName);
        updateToolbarMeta();
        window.requestAnimationFrame(refreshGridWidths);
    }

    function focusFilter() {
        setActiveTab('data');
        refs.searchInput.focus();
        refs.searchInput.select();
        refs.searchInput.classList.add('sqlm-focus-ring');
        window.setTimeout(() => refs.searchInput.classList.remove('sqlm-focus-ring'), 1200);
    }

    async function quickSort() {
        if (!state.context) {
            return;
        }

        if (hasActiveTransaction()) {
            showFlash('Hãy commit hoặc rollback trước khi đổi thứ tự dữ liệu.', 'warning');
            return;
        }

        setActiveTab('data');
        const nextDirection = refs.sortDirection.value === 'asc' ? 'desc' : 'asc';
        const identityKeys = rowIdentityKeys(state.context);
        const fallbackColumn = state.context.filters.sort_column || identityKeys[0] || state.context.primary_keys[0] || (state.context.columns[0] ? state.context.columns[0].name : '');
        refs.sortColumn.value = refs.sortColumn.value || fallbackColumn;
        refs.sortDirection.value = nextDirection;
        await loadTable(state.selectedTable, {
            page: 1,
            sort_column: refs.sortColumn.value,
            sort_direction: nextDirection,
        });
    }

    function parseCsvLine(line) {
        const result = [];
        let current = '';
        let inQuotes = false;

        for (let index = 0; index < line.length; index += 1) {
            const character = line[index];
            const nextCharacter = line[index + 1];

            if (character === '"') {
                if (inQuotes && nextCharacter === '"') {
                    current += '"';
                    index += 1;
                    continue;
                }

                inQuotes = !inQuotes;
                continue;
            }

            if (character === ',' && !inQuotes) {
                result.push(current);
                current = '';
                continue;
            }

            current += character;
        }

        result.push(current);
        return result;
    }

    function parseCsv(text) {
        const content = String(text || '').replace(/^\uFEFF/, '');
        const lines = content.split(/\r?\n/).filter((line, index, allLines) => !(index === allLines.length - 1 && line.trim() === ''));
        if (lines.length < 2) {
            return [];
        }

        const headers = parseCsvLine(lines[0]).map((header) => header.trim());
        return lines.slice(1).reduce((records, line) => {
            if (line.trim() === '') {
                return records;
            }

            const values = parseCsvLine(line);
            const record = {};
            headers.forEach((header, index) => {
                if (header !== '') {
                    record[header] = values[index] !== undefined ? values[index] : '';
                }
            });
            records.push(record);
            return records;
        }, []);
    }

    function buildImportValueSet(record) {
        const values = {};
        const columnMap = new Map((state.context.columns || []).map((column) => [column.name, column]));

        Object.entries(record).forEach(([key, rawValue]) => {
            if (!columnMap.has(key)) {
                return;
            }

            values[key] = {
                value: rawValue === null || rawValue === undefined ? '' : String(rawValue),
                is_null: rawValue === null,
            };
        });

        return values;
    }

    async function runBatchInsert(valuesList) {
        const operations = valuesList.map((values) => ({
            type: 'insert',
            table: state.context.table,
            values,
        }));

        const params = new URLSearchParams();
        params.set('_csrf', state.csrfToken);
        params.set('operations_json', JSON.stringify(operations));

        return requestJson(endpoints.commit, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-TOKEN': state.csrfToken,
            },
            body: params.toString(),
        });
    }

    async function handleImportFile(file) {
        if (/\.sql$/i.test(file.name) || /sql/i.test(file.type || '')) {
            if (hasActiveTransaction()) {
                showFlash('Hãy commit hoặc rollback transaction đang stage trước khi import SQL trực tiếp.', 'warning');
                return;
            }

            refs.importBtn.disabled = true;
            try {
                const firstPass = await requestImport(file, false);
                const firstPayload = firstPass.payload || {};
                state.importReport = {
                    success: !!firstPayload.success,
                    message: firstPayload.message || '',
                    result: firstPayload.result || null,
                };

                if (state.importReport.result && state.importReport.result.module_health) {
                    state.healthSummary = state.importReport.result.module_health;
                }

                renderImportPanel();
                updateStatusBar();

                if (firstPass.ok) {
                    showFlash(firstPayload.message || 'Đã import SQL thành công.', 'success');
                    try {
                        await loadTable(state.selectedTable, { page: 1, allowDuringTransaction: true });
                    } catch (loadError) {
                        showFlash('Import đã chạy xong nhưng bảng hiện tại không thể tải lại. Kiểm tra module health ở mode Nhập.', 'warning');
                    }
                    return;
                }

                if (firstPayload.result && firstPayload.result.requires_confirmation) {
                    showFlash(firstPayload.message || 'File SQL cần xác nhận trước khi chạy.', 'warning');
                    const confirmed = window.confirm(`${firstPayload.message || 'File SQL có chứa DDL/mixed statements.'}\n\nBạn có muốn tiếp tục chạy file này không?`);
                    if (!confirmed) {
                        return;
                    }

                    const secondPass = await requestImport(file, true);
                    const secondPayload = secondPass.payload || {};
                    state.importReport = {
                        success: !!secondPayload.success,
                        message: secondPayload.message || '',
                        result: secondPayload.result || null,
                    };

                    if (state.importReport.result && state.importReport.result.module_health) {
                        state.healthSummary = state.importReport.result.module_health;
                    }

                    renderImportPanel();
                    updateStatusBar();

                    if (secondPass.ok) {
                        showFlash(secondPayload.message || 'Đã import SQL thành công.', 'success');
                        try {
                            await loadTable(state.selectedTable, { page: 1, allowDuringTransaction: true });
                        } catch (loadError) {
                            showFlash('Import đã chạy xong nhưng bảng hiện tại không thể tải lại. Kiểm tra module health ở mode Nhập.', 'warning');
                        }
                        return;
                    }

                    throw new Error(secondPayload.message || 'Import SQL thất bại.');
                }

                throw new Error(firstPayload.message || 'Import SQL thất bại.');
            } finally {
                refs.importBtn.disabled = false;
            }
        }

        if (!state.context) {
            showFlash('Hãy chọn một bảng trước khi import CSV hoặc JSON.', 'warning');
            return;
        }

        const rawText = await file.text();
        let records = [];

        if (/\.json$/i.test(file.name) || file.type === 'application/json') {
            const decoded = JSON.parse(rawText);
            if (Array.isArray(decoded)) {
                records = decoded;
            } else if (decoded && Array.isArray(decoded.data)) {
                records = decoded.data;
            } else {
                throw new Error('File JSON phải là một mảng object hoặc có khóa data là mảng.');
            }
        } else {
            records = parseCsv(rawText);
        }

        const valuesList = records
            .filter((record) => record && typeof record === 'object' && !Array.isArray(record))
            .map(buildImportValueSet)
            .filter((values) => Object.keys(values).length > 0);

        if (valuesList.length === 0) {
            showFlash('File import không có dòng hợp lệ khớp với cột của bảng hiện tại.', 'warning');
            return;
        }

        if (valuesList.length > 200 && !window.confirm(`File có ${valuesList.length} dòng. Bạn có chắc muốn import toàn bộ?`)) {
            return;
        }

        if (hasActiveTransaction()) {
            valuesList.forEach((values) => {
                const operationId = createOperationId();
                queueOperation({ id: operationId, type: 'insert', table: state.context.table, values });
                applyLocalInsert(values, operationId);
            });
            renderDataPanel();
            updateToolbarMeta();
            showFlash(`Đã stage ${valuesList.length} bản ghi từ file import.`, 'info');
            return;
        }

        refs.importBtn.disabled = true;
        try {
            const payload = await runBatchInsert(valuesList);
            state.importReport = {
                success: true,
                message: payload.message || `Đã import ${valuesList.length} bản ghi.`,
                result: {
                    analysis: {
                        statement_count: valuesList.length,
                        affected_modules: [],
                        rollback_supported: true,
                        warnings: [],
                    },
                    execution: {
                        successful_statements: valuesList.length,
                        duration_ms: 0,
                    },
                    module_health: state.healthSummary,
                },
            };
            renderImportPanel();
            updateStatusBar();
            showFlash(payload.message || `Đã import ${valuesList.length} bản ghi.`, 'success');
            await loadTable(state.selectedTable, { page: 1, allowDuringTransaction: true });
        } finally {
            refs.importBtn.disabled = false;
        }
    }

    function toCsvValue(value) {
        if (value === null || value === undefined) {
            return '';
        }

        const text = String(value).replace(/"/g, '""');
        return /[",\n]/.test(text) ? `"${text}"` : text;
    }

    function exportCurrentRows() {
        if (!state.context || !Array.isArray(state.context.rows) || state.context.rows.length === 0) {
            showFlash('Không có dữ liệu để export.', 'warning');
            return;
        }

        const headers = state.context.columns.map((column) => column.name);
        const lines = [headers.map(toCsvValue).join(',')];
        state.context.rows.forEach((row) => {
            lines.push(headers.map((header) => toCsvValue(row[header])).join(','));
        });

        const blob = new Blob([`\uFEFF${lines.join('\r\n')}`], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        const stamp = new Date().toISOString().replace(/[:.]/g, '-');
        anchor.href = url;
        anchor.download = `${state.context.table}-${stamp}.csv`;
        document.body.appendChild(anchor);
        anchor.click();
        document.body.removeChild(anchor);
        window.URL.revokeObjectURL(url);
        showFlash('Đã export dữ liệu trang hiện tại sang CSV.', 'success');
    }

    function filenameFromDisposition(disposition, fallback) {
        if (!disposition) {
            return fallback;
        }

        const utfMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i);
        if (utfMatch && utfMatch[1]) {
            try {
                return decodeURIComponent(utfMatch[1].replace(/"/g, ''));
            } catch (error) {
                return utfMatch[1].replace(/"/g, '');
            }
        }

        const match = disposition.match(/filename="?([^"]+)"?/i);
        return match && match[1] ? match[1] : fallback;
    }

    async function downloadSqlDump(options = {}) {
        if (!endpoints.exportSql) {
            showFlash('Chưa cấu hình endpoint export SQL.', 'danger');
            return;
        }

        const params = new URLSearchParams();
        params.set('_csrf', state.csrfToken);
        params.set('scope', options.scope || 'database');
        params.set('table', options.table || '');
        params.set('include_schema', options.includeSchema === false ? '0' : '1');
        params.set('include_data', options.includeData === false ? '0' : '1');
        params.set('add_drop', options.addDrop === false ? '0' : '1');
        params.set('insert_mode', options.insertMode || 'INSERT');

        try {
            showFlash('Đang tạo file SQL dump...', 'info');
            const response = await fetch(endpoints.exportSql, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/sql, application/json;q=0.9',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-TOKEN': state.csrfToken,
                },
                body: params.toString(),
            });

            const nextToken = response.headers.get('X-CSRF-TOKEN');
            if (nextToken) {
                state.csrfToken = nextToken;
            }

            const contentType = response.headers.get('Content-Type') || '';
            const isSqlDownload = contentType.includes('application/sql')
                || contentType.includes('application/octet-stream')
                || contentType.includes('text/plain');
            if (!response.ok || contentType.includes('application/json') || !isSqlDownload) {
                const text = await response.text();
                let payload = null;
                try {
                    payload = JSON.parse(text);
                } catch (error) {
                    throw new Error('Export SQL thất bại hoặc phiên admin đã hết hạn.');
                }

                if (payload && payload.csrf_token) {
                    state.csrfToken = payload.csrf_token;
                }

                throw new Error(payload && payload.message ? payload.message : 'Export SQL thất bại.');
            }

            const blob = await response.blob();
            const stamp = new Date().toISOString().replace(/[:.]/g, '-');
            const fallback = `zenoxdigital-${stamp}.sql`;
            const filename = filenameFromDisposition(response.headers.get('Content-Disposition'), fallback);
            const url = window.URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = filename;
            document.body.appendChild(anchor);
            anchor.click();
            document.body.removeChild(anchor);
            window.URL.revokeObjectURL(url);
            showFlash('Đã tải file SQL. Khi import phpMyAdmin, chọn file này và chạy trực tiếp.', 'success');
        } catch (error) {
            showFlash(error.message || 'Export SQL thất bại.', 'danger');
        }
    }

    function generatedValueForColumn(column, rowNumber) {
        const name = String(column.name || '').toLowerCase();
        const type = String(column.data_type || column.column_type || '').toLowerCase();
        const now = new Date();
        const pad = (value) => String(value).padStart(2, '0');
        const dateValue = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
        const dateTimeValue = `${dateValue} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;

        if (isGeneratedColumn(column) || /auto_increment/i.test(String(column.extra || ''))) return '';
        if (name.includes('email')) return `sample${rowNumber}@local.test`;
        if (name.includes('username')) return `user_${rowNumber}`;
        if (name.includes('full_name') || name === 'name') return `Sample User ${rowNumber}`;
        if (name.includes('phone')) return `090000${String(rowNumber).padStart(4, '0')}`;
        if (name.includes('address')) return 'Ho Chi Minh City';
        if (name.includes('password')) return '123456';
        if (name.includes('slug')) return `sample-item-${rowNumber}`;
        if (name.includes('title')) return `Sample record ${rowNumber}`;
        if (name.includes('description') || name.includes('content')) return `Generated content ${rowNumber}`;
        if (type.includes('json')) return JSON.stringify({ sample: true, index: rowNumber });
        if (type.includes('datetime') || type.includes('timestamp')) return dateTimeValue;
        if (type === 'date') return dateValue;
        if (type.includes('time')) return `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
        if (name.startsWith('is_') || name.startsWith('has_') || name.includes('status') || name.includes('enabled')) return '1';
        if (isNumericColumn(column)) return String(rowNumber);
        return `Sample ${column.name} ${rowNumber}`;
    }

    function generateSampleRow() {
        if (!state.context) {
            return;
        }

        const rowNumber = Number(state.context.pagination.total || 0) + 1;
        const generated = {};
        state.context.columns.forEach((column) => {
            generated[column.name] = generatedValueForColumn(column, rowNumber);
        });
        openRowModal('insert', generated);
    }

    function getNumericChartColumns() {
        if (!state.context) {
            return [];
        }

        return state.context.columns.filter((column) => isNumericColumn(column) || state.context.rows.some((row) => Number.isFinite(Number(row[column.name]))));
    }

    function buildChartPalette(size) {
        const palette = ['#2563eb', '#0ea5e9', '#14b8a6', '#22c55e', '#f59e0b', '#f97316', '#ef4444', '#8b5cf6', '#ec4899'];
        return Array.from({ length: size }, (_, index) => palette[index % palette.length]);
    }

    function populateChartSelectors() {
        if (!state.context) {
            return false;
        }

        const numericColumns = getNumericChartColumns();
        const allColumns = state.context.columns || [];
        refs.chartLabelColumn.innerHTML = allColumns.map((column) => `<option value="${escapeHtml(column.name)}">${escapeHtml(column.name)}</option>`).join('');
        refs.chartValueColumn.innerHTML = numericColumns.map((column) => `<option value="${escapeHtml(column.name)}">${escapeHtml(column.name)}</option>`).join('');

        if (allColumns.length > 0) refs.chartLabelColumn.value = allColumns[0].name;
        if (numericColumns.length > 0) refs.chartValueColumn.value = numericColumns[0].name;
        return numericColumns.length > 0;
    }

    function renderChart() {
        if (!state.context || !window.Chart) {
            showFlash('Chart.js chưa sẵn sàng để dựng biểu đồ.', 'warning');
            return;
        }

        const labelColumn = refs.chartLabelColumn.value;
        const valueColumn = refs.chartValueColumn.value;
        const rows = state.context.rows
            .map((row) => ({ label: toDisplayString(row[labelColumn]) || '(trống)', value: Number(row[valueColumn]) }))
            .filter((item) => Number.isFinite(item.value));

        if (state.chartInstance) {
            state.chartInstance.destroy();
            state.chartInstance = null;
        }

        if (rows.length === 0) {
            refs.chartEmpty.classList.remove('d-none');
            refs.chartCanvas.classList.add('d-none');
            return;
        }

        refs.chartEmpty.classList.add('d-none');
        refs.chartCanvas.classList.remove('d-none');

        const colors = buildChartPalette(rows.length);
        state.chartInstance = new window.Chart(refs.chartCanvas, {
            type: refs.chartType.value || 'bar',
            data: {
                labels: rows.map((row) => row.label),
                datasets: [{
                    label: valueColumn,
                    data: rows.map((row) => row.value),
                    backgroundColor: colors.map((color) => `${color}cc`),
                    borderColor: colors,
                    borderWidth: 1.5,
                    tension: 0.3,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: refs.chartType.value === 'pie' || refs.chartType.value === 'doughnut',
                    },
                },
            },
        });
    }

    function openChartBuilder() {
        if (!state.context || !state.context.rows.length) {
            showFlash('Cần có dữ liệu trong bảng hiện tại để tạo chart.', 'warning');
            return;
        }

        const ready = populateChartSelectors();
        if (!ready) {
            refs.chartEmpty.classList.remove('d-none');
            refs.chartCanvas.classList.add('d-none');
        }

        if (chartModal) {
            chartModal.show();
        }

        if (ready) {
            renderChart();
        }
    }

    /* EVENTS */

    document.querySelectorAll('[data-sqlm-tab]').forEach((tab) => {
        tab.addEventListener('click', () => setActiveTab(tab.getAttribute('data-sqlm-tab') || 'data'));
    });

    if (refs.databaseToggle) {
        refs.databaseToggle.addEventListener('click', () => {
            state.databaseExpanded = !state.databaseExpanded;
            renderTableList();
        });
    }

    if (refs.explorerToggleBtn) {
        refs.explorerToggleBtn.addEventListener('click', () => {
            state.explorerCollapsed = !state.explorerCollapsed;
            updateToolbarMeta();
        });
    }

    if (refs.explorerSplitter) {
        refs.explorerSplitter.addEventListener('pointerdown', startExplorerResize);
        refs.explorerSplitter.addEventListener('dblclick', () => {
            state.explorerWidth = 286;
            applyExplorerWidth();
            window.localStorage.setItem('sqlm:explorerWidth', '286');
        });
    }

    document.addEventListener('click', closeContextMenu);
    document.addEventListener('keydown', moveCellByArrow);

    app.addEventListener('click', (event) => {
        const actionTrigger = event.target.closest('[data-sqlm-action]');
        if (!actionTrigger) {
            return;
        }

        const action = actionTrigger.getAttribute('data-sqlm-action') || '';
        if (action === 'focus-filter') {
            focusFilter();
        } else if (action === 'apply-filter') {
            refs.applyBtn.click();
        } else if (action === 'quick-sort') {
            quickSort();
        } else if (action === 'insert') {
            refs.insertBtn.click();
        } else if (action === 'generate') {
            refs.generateBtn.click();
        } else if (action === 'export') {
            refs.exportBtn.click();
        } else if (action === 'export-sql-full') {
            downloadSqlDump({
                scope: 'database',
                includeSchema: true,
                includeData: true,
                addDrop: true,
                insertMode: 'INSERT',
            });
        } else if (action === 'export-sql-table') {
            downloadSqlDump({
                scope: 'table',
                table: state.context ? state.context.table : state.selectedTable,
                includeSchema: true,
                includeData: true,
                addDrop: true,
                insertMode: 'INSERT',
            });
        } else if (action === 'export-sql-schema') {
            downloadSqlDump({
                scope: 'database',
                includeSchema: true,
                includeData: false,
                addDrop: true,
                insertMode: 'INSERT',
            });
        } else if (action === 'export-sql-data') {
            downloadSqlDump({
                scope: 'database',
                includeSchema: false,
                includeData: true,
                addDrop: false,
                insertMode: 'REPLACE',
            });
        } else if (action === 'auto-fit-columns') {
            autoFitAllColumns();
        } else if (action === 'reset-column-widths') {
            resetColumnWidths();
        } else if (action === 'chart') {
            refs.chartBtn.click();
        } else if (action === 'import') {
            refs.importBtn.click();
        } else if (action === 'begin-txn') {
            refs.txnBtn.click();
        } else if (action === 'commit') {
            refs.commitBtn.click();
        } else if (action === 'rollback') {
            refs.rollbackBtn.click();
        }
    });

    refs.tableSearch.addEventListener('input', renderTableList);
    refs.reloadBtn.addEventListener('click', () => loadTable(state.selectedTable, { page: 1 }));
    refs.applyBtn.addEventListener('click', () => loadTable(state.selectedTable, { page: 1 }));
    refs.searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            refs.applyBtn.click();
        }
    });
    refs.insertBtn.addEventListener('click', () => openRowModal('insert'));
    refs.editBtn.addEventListener('click', () => openRowModal('update'));
    refs.deleteBtn.addEventListener('click', deleteSelectedRow);
    refs.saveRowBtn.addEventListener('click', saveRowForm);
    refs.runQueryBtn.addEventListener('click', runQuery);
    refs.clearQueryBtn.addEventListener('click', () => {
        setDefaultQuery();
        state.queryResult = null;
        state.queryError = null;
        renderQueryPanel();
        updateStatusBar();
    });
    refs.txnBtn.addEventListener('click', startTransaction);
    refs.commitBtn.addEventListener('click', commitTransaction);
    refs.rollbackBtn.addEventListener('click', rollbackTransaction);
    refs.wrapBtn.addEventListener('click', () => {
        state.wrapText = !state.wrapText;
        updateToolbarMeta();
    });
    refs.focusFilterBtn.addEventListener('click', focusFilter);
    refs.quickSortBtn.addEventListener('click', quickSort);
    refs.importBtn.addEventListener('click', () => refs.importInput.click());
    refs.importInput.addEventListener('change', async () => {
        const file = refs.importInput.files && refs.importInput.files[0];
        if (!file) {
            return;
        }

        try {
            await handleImportFile(file);
        } catch (error) {
            showFlash(error.message, 'danger');
        } finally {
            refs.importInput.value = '';
        }
    });
    refs.exportBtn.addEventListener('click', exportCurrentRows);
    refs.generateBtn.addEventListener('click', generateSampleRow);
    refs.chartBtn.addEventListener('click', openChartBuilder);
    refs.renderChartBtn.addEventListener('click', renderChart);
    refs.chartType.addEventListener('change', renderChart);
    refs.chartLabelColumn.addEventListener('change', renderChart);
    refs.chartValueColumn.addEventListener('change', renderChart);
    refs.prevPageBtn.addEventListener('click', () => {
        if (!state.context || state.activeTab !== 'data') {
            return;
        }

        const currentPage = Number(state.context.pagination.current_page || 1);
        loadTable(state.selectedTable, { page: currentPage - 1 });
    });
    refs.nextPageBtn.addEventListener('click', () => {
        if (!state.context || state.activeTab !== 'data') {
            return;
        }

        const currentPage = Number(state.context.pagination.current_page || 1);
        loadTable(state.selectedTable, { page: currentPage + 1 });
    });

    window.addEventListener('resize', () => {
        window.requestAnimationFrame(refreshGridWidths);
    });

    if (chartModalElement) {
        chartModalElement.addEventListener('hidden.bs.modal', () => {
            if (state.chartInstance) {
                state.chartInstance.destroy();
                state.chartInstance = null;
            }
        });
    }

    state.context = decorateContext(bootstrapData.context || null);
    if (state.selectedTable) {
        state.expandedTables[state.selectedTable] = true;
    }
    loadColumnWidths();
    applyExplorerWidth();
    if (refs.queryInput && !refs.queryInput.value.trim() && state.selectedTable) {
        setDefaultQuery();
    }

    renderAll();
});
