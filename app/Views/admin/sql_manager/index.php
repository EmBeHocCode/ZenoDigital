<?php
$tables = is_array($tables ?? null) ? $tables : [];
$initialContext = is_array($initialContext ?? null) ? $initialContext : null;
$databaseLabel = (string) ($databaseName ?? 'N/A');
$selectedTableLabel = (string) ($selectedTable ?? 'N/A');
$bootstrapPayload = [
    'database_name' => (string) ($databaseName ?? ''),
    'selected_table' => (string) ($selectedTable ?? ''),
    'tables' => $tables,
    'context' => $initialContext,
    'csrf_token' => (string) ($initialCsrfToken ?? ''),
    'module_health' => is_array($moduleHealthSummary ?? null) ? $moduleHealthSummary : ['modules' => [], 'unhealthy_modules' => []],
];
?>

<div class="sql-manager-page">
    <div class="sqlm-shell" id="sqlManagerApp"
         data-table-url="<?= e(base_url('admin/sql-manager/table-data')) ?>"
         data-query-url="<?= e(base_url('admin/sql-manager/query')) ?>"
         data-commit-url="<?= e(base_url('admin/sql-manager/commit-batch')) ?>"
         data-import-url="<?= e(base_url('admin/sql-manager/import')) ?>"
         data-insert-url="<?= e(base_url('admin/sql-manager/insert-row')) ?>"
         data-update-url="<?= e(base_url('admin/sql-manager/update-row')) ?>"
         data-delete-url="<?= e(base_url('admin/sql-manager/delete-row')) ?>">

        <div id="sqlmFlash" class="sqlm-flash"></div>

        <div class="sqlm-layout">
            <aside class="sqlm-explorer" aria-label="Database explorer">
                <div class="sqlm-explorer-head">
                    <div class="sqlm-section-kicker">SQL Manager</div>
                    <h1 class="sqlm-explorer-title">Explorer</h1>
                    <p class="sqlm-explorer-subtitle">Tree browser kiểu phpMyAdmin, tối ưu cho thao tác nhanh trên bảng hiện tại.</p>
                    <div class="sqlm-explorer-pills">
                        <span class="sqlm-side-pill"><i class="fas fa-database"></i><?= e($databaseLabel) ?></span>
                        <span class="sqlm-side-pill" id="sqlmTableCountPill"><i class="fas fa-table"></i><?= count($tables) ?> bảng</span>
                    </div>
                </div>

                <div class="sqlm-search-wrap">
                    <label class="sqlm-search-label" for="sqlmTableSearch">Tìm database / bảng</label>
                    <div class="sqlm-search-box">
                        <i class="fas fa-magnifying-glass"></i>
                        <input id="sqlmTableSearch" class="form-control" type="search" placeholder="Lọc theo tên bảng...">
                    </div>
                </div>

                <div class="sqlm-tree-browser">
                    <div class="sqlm-tree-root" data-sqlm-db-node>
                        <button type="button" id="sqlmDatabaseToggle" class="sqlm-db-node is-open" aria-expanded="true">
                            <span class="sqlm-db-node-main">
                                <span class="sqlm-db-icon"><i class="fas fa-database"></i></span>
                                <span class="sqlm-db-text">
                                    <strong><?= e($databaseLabel) ?></strong>
                                    <small id="sqlmDatabaseStatus">Base schema hiện tại</small>
                                </span>
                            </span>
                            <span class="sqlm-db-caret"><i class="fas fa-chevron-down"></i></span>
                        </button>

                        <div class="sqlm-tree-children" id="sqlmTableList"></div>
                    </div>
                </div>

                <div class="sqlm-explorer-foot">
                    <div class="sqlm-foot-item">
                        <span class="sqlm-foot-label">Active table</span>
                        <strong id="sqlmCurrentTable"><?= e($selectedTableLabel) ?></strong>
                    </div>
                    <div class="sqlm-foot-item">
                        <span class="sqlm-foot-label">Mode</span>
                        <strong id="sqlmExplorerState">Data</strong>
                    </div>
                </div>
            </aside>

            <main class="sqlm-workspace" aria-label="SQL workspace">
                <section class="sqlm-headbar">
                    <div class="sqlm-head-main">
                        <div class="sqlm-section-kicker">Workbench</div>
                        <div class="sqlm-headline-row">
                            <h2 class="sqlm-title">SQL Workspace</h2>
                            <span class="sqlm-workbench-chip"><i class="fas fa-server"></i><?= e($databaseLabel) ?></span>
                            <span class="sqlm-workbench-chip is-table"><i class="fas fa-table"></i><span id="sqlmHeadTable"><?= e($selectedTableLabel) ?></span></span>
                        </div>
                        <p class="sqlm-subtitle">Main area ưu tiên grid, query console và thao tác bảng. Bớt card thừa, tập trung vào object hiện tại.</p>
                    </div>

                    <div class="sqlm-head-aside">
                        <div class="sqlm-head-meta">
                            <span class="sqlm-head-meta-label">Current mode</span>
                            <strong id="sqlmModeTitle">Dữ liệu</strong>
                            <p id="sqlmModeDescription" class="mb-0">Xem, lọc, sửa và duyệt bản ghi theo kiểu table browser.</p>
                        </div>
                        <span class="sqlm-mode-badge" id="sqlmModeBadge">Data</span>
                    </div>
                </section>

                <section class="sqlm-meta-strip">
                    <span class="sqlm-meta-pill" id="sqlmPrimaryKeys">Row key: -</span>
                    <span class="sqlm-meta-pill" id="sqlmRowCount">0 bản ghi</span>
                    <span class="sqlm-meta-pill" id="sqlmEngine">Engine: -</span>
                    <span class="sqlm-meta-pill" id="sqlmTxnState">Transaction: Off</span>
                </section>

                <nav class="sqlm-mode-tabs" aria-label="SQL Manager modes">
                    <button type="button" class="sqlm-mode-tab is-active" data-sqlm-tab="data">Dữ liệu</button>
                    <button type="button" class="sqlm-mode-tab" data-sqlm-tab="structure">Cấu trúc</button>
                    <button type="button" class="sqlm-mode-tab" data-sqlm-tab="query">SQL</button>
                    <button type="button" class="sqlm-mode-tab" data-sqlm-tab="search">Tìm kiếm</button>
                    <button type="button" class="sqlm-mode-tab" data-sqlm-tab="insert">Chèn</button>
                    <button type="button" class="sqlm-mode-tab" data-sqlm-tab="export">Xuất</button>
                    <button type="button" class="sqlm-mode-tab" data-sqlm-tab="import">Nhập</button>
                    <button type="button" class="sqlm-mode-tab" data-sqlm-tab="operations">Thao tác</button>
                </nav>

                <section class="sqlm-mode-toolbar">
                    <div class="sqlm-toolbar-group is-active" data-sqlm-toolbar="data">
                        <button id="sqlmReloadBtn" type="button" class="btn btn-light border"><i class="fas fa-rotate"></i><span>Tải lại</span></button>
                        <button id="sqlmInsertBtn" type="button" class="btn btn-primary"><i class="fas fa-plus"></i><span>Thêm dòng</span></button>
                        <button id="sqlmEditBtn" type="button" class="btn btn-light border" disabled><i class="fas fa-pen-to-square"></i><span>Sửa</span></button>
                        <button id="sqlmDeleteBtn" type="button" class="btn btn-light border border-danger text-danger" disabled><i class="fas fa-trash"></i><span>Xóa</span></button>
                        <button id="sqlmFocusFilterBtn" type="button" class="btn btn-light border"><i class="fas fa-filter"></i><span>Filter</span></button>
                        <button id="sqlmQuickSortBtn" type="button" class="btn btn-light border"><i class="fas fa-arrow-down-wide-short"></i><span>Sort</span></button>
                        <button id="sqlmWrapBtn" type="button" class="btn btn-light border"><i class="fas fa-align-left"></i><span>Text</span></button>
                    </div>

                    <div class="sqlm-toolbar-group" data-sqlm-toolbar="structure">
                        <button type="button" class="btn btn-light border" disabled><i class="fas fa-columns"></i><span>Thêm cột</span></button>
                        <button type="button" class="btn btn-light border" disabled><i class="fas fa-key"></i><span>Index</span></button>
                        <button type="button" class="btn btn-light border" disabled><i class="fas fa-diagram-project"></i><span>Relation</span></button>
                    </div>

                    <div class="sqlm-toolbar-group" data-sqlm-toolbar="query">
                        <button id="sqlmRunQueryBtn" type="button" class="btn btn-primary"><i class="fas fa-play"></i><span>Run</span></button>
                        <button id="sqlmClearQueryBtn" type="button" class="btn btn-light border"><i class="fas fa-eraser"></i><span>Clear</span></button>
                        <button type="button" class="btn btn-light border" disabled><i class="fas fa-circle-info"></i><span>Explain</span></button>
                        <button type="button" class="btn btn-light border" disabled><i class="fas fa-indent"></i><span>Format</span></button>
                    </div>

                    <div class="sqlm-toolbar-group" data-sqlm-toolbar="search">
                        <button type="button" class="btn btn-primary" data-sqlm-action="focus-filter"><i class="fas fa-filter"></i><span>Mở bộ lọc</span></button>
                        <button id="sqlmApplyBtn" type="button" class="btn btn-light border"><i class="fas fa-magnifying-glass"></i><span>Áp dụng</span></button>
                        <button type="button" class="btn btn-light border" data-sqlm-action="quick-sort"><i class="fas fa-arrow-down-wide-short"></i><span>Sort</span></button>
                    </div>

                    <div class="sqlm-toolbar-group" data-sqlm-toolbar="insert">
                        <button type="button" class="btn btn-primary" data-sqlm-action="insert"><i class="fas fa-plus"></i><span>Thêm dòng mới</span></button>
                        <button id="sqlmGenerateBtn" type="button" class="btn btn-light border"><i class="fas fa-wand-magic-sparkles"></i><span>Dữ liệu mẫu</span></button>
                    </div>

                    <div class="sqlm-toolbar-group" data-sqlm-toolbar="export">
                        <button id="sqlmExportBtn" type="button" class="btn btn-primary"><i class="fas fa-file-export"></i><span>Export CSV</span></button>
                        <button id="sqlmChartBtn" type="button" class="btn btn-light border"><i class="fas fa-chart-column"></i><span>Tạo chart</span></button>
                    </div>

                    <div class="sqlm-toolbar-group" data-sqlm-toolbar="import">
                        <button id="sqlmImportBtn" type="button" class="btn btn-primary"><i class="fas fa-file-import"></i><span>Chọn file</span></button>
                        <span class="sqlm-toolbar-note">SQL, CSV hoặc JSON. File `.sql` sẽ chạy qua preflight và báo rollback thực tế.</span>
                    </div>

                    <div class="sqlm-toolbar-group" data-sqlm-toolbar="operations">
                        <button id="sqlmTxnBtn" type="button" class="btn btn-light border"><i class="fas fa-play-circle"></i><span>Begin</span></button>
                        <button id="sqlmCommitBtn" type="button" class="btn btn-success d-none" disabled><i class="fas fa-check"></i><span>Commit</span></button>
                        <button id="sqlmRollbackBtn" type="button" class="btn btn-outline-danger d-none" disabled><i class="fas fa-rotate-left"></i><span>Rollback</span></button>
                        <button type="button" class="btn btn-light border" disabled><i class="fas fa-broom"></i><span>Optimize</span></button>
                        <button type="button" class="btn btn-light border" disabled><i class="fas fa-i-cursor"></i><span>Rename</span></button>
                        <button type="button" class="btn btn-light border" disabled><i class="fas fa-ban"></i><span>Truncate</span></button>
                    </div>

                    <input id="sqlmImportInput" type="file" class="d-none" accept=".sql,.csv,.json,text/plain,text/csv,application/json,application/sql">
                </section>

                <section class="sqlm-filter-row" id="sqlmFilterRow">
                    <div class="sqlm-filter-meta">
                        <div>
                            <div class="sqlm-filter-label">Search / Filter</div>
                            <div class="sqlm-filter-help">Dùng bộ lọc chung cho mode Dữ liệu và Tìm kiếm. Không đổi backend query hiện tại.</div>
                        </div>
                    </div>

                    <div class="sqlm-filters">
                        <div class="sqlm-filter-grow">
                            <label class="form-label" for="sqlmSearchInput">Tìm trong bảng</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-magnifying-glass"></i></span>
                                <input id="sqlmSearchInput" class="form-control" placeholder="Tìm theo nội dung các cột...">
                            </div>
                        </div>
                        <div>
                            <label class="form-label" for="sqlmPerPage">Hiển thị</label>
                            <select id="sqlmPerPage" class="form-select">
                                <option value="25">25 dòng</option>
                                <option value="50">50 dòng</option>
                                <option value="100">100 dòng</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="sqlmSortColumn">Sort cột</label>
                            <select id="sqlmSortColumn" class="form-select"></select>
                        </div>
                        <div>
                            <label class="form-label" for="sqlmSortDirection">Hướng</label>
                            <select id="sqlmSortDirection" class="form-select">
                                <option value="asc">ASC</option>
                                <option value="desc">DESC</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="sqlm-table-container">
                    <div class="sqlm-panel" data-sqlm-panel="data">
                        <div id="sqlmDataPanel" class="sqlm-panel-body"></div>
                    </div>

                    <div class="sqlm-panel d-none" data-sqlm-panel="structure">
                        <div id="sqlmStructurePanel" class="sqlm-panel-body"></div>
                    </div>

                    <div class="sqlm-panel d-none" data-sqlm-panel="query">
                        <div class="sqlm-query-shell">
                            <div class="sqlm-query-shell-head">
                                <div>
                                    <h3 class="sqlm-panel-title">SQL Console</h3>
                                    <p class="sqlm-panel-subtitle">Chỉ cho phép `SELECT`, `SHOW`, `DESCRIBE`, `EXPLAIN`. Tập trung vào query đọc dữ liệu an toàn.</p>
                                </div>
                            </div>
                            <div class="sqlm-console">
                                <textarea id="sqlmQueryInput" class="sqlm-query-input" spellcheck="false" placeholder="SELECT * FROM `users` LIMIT 100"></textarea>
                            </div>
                        </div>
                        <div id="sqlmQueryPanel" class="sqlm-panel-body sqlm-query-results"></div>
                    </div>

                    <div class="sqlm-panel d-none" data-sqlm-panel="search">
                        <div id="sqlmSearchPanel" class="sqlm-panel-body"></div>
                    </div>

                    <div class="sqlm-panel d-none" data-sqlm-panel="insert">
                        <div id="sqlmInsertPanel" class="sqlm-panel-body"></div>
                    </div>

                    <div class="sqlm-panel d-none" data-sqlm-panel="export">
                        <div id="sqlmExportPanel" class="sqlm-panel-body"></div>
                    </div>

                    <div class="sqlm-panel d-none" data-sqlm-panel="import">
                        <div id="sqlmImportPanel" class="sqlm-panel-body"></div>
                    </div>

                    <div class="sqlm-panel d-none" data-sqlm-panel="operations">
                        <div id="sqlmOperationsPanel" class="sqlm-panel-body"></div>
                    </div>
                </section>

                <footer class="sqlm-statusbar">
                    <div class="sqlm-status-main">
                        <span class="sqlm-status-item" id="sqlmStatusLabel">Ready</span>
                        <span class="sqlm-status-item" id="sqlmStatusSummary">0 bản ghi</span>
                        <span class="sqlm-status-item" id="sqlmStatusSelection">Chưa chọn dòng</span>
                    </div>
                    <div class="sqlm-status-actions">
                        <button type="button" class="btn btn-light border btn-sm" id="sqlmPrevPageBtn">Trang trước</button>
                        <button type="button" class="btn btn-light border btn-sm" id="sqlmNextPageBtn">Trang sau</button>
                    </div>
                </footer>
            </main>
        </div>
    </div>
</div>

<div class="modal fade" id="sqlmRowModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="sqlmRowModalTitle">Chỉnh sửa dòng</h5>
                    <div class="small text-secondary" id="sqlmRowModalSubtitle">Cập nhật dữ liệu trực tiếp theo cột.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <form id="sqlmRowForm" class="sqlm-row-form"></form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="sqlmSaveRowBtn">Lưu thay đổi</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sqlmChartModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Create Chart</h5>
                    <div class="small text-secondary">Dùng dữ liệu hiện tại để dựng chart nhanh cho bảng đang mở.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="sqlm-chart-controls">
                    <div>
                        <label class="form-label">Chart type</label>
                        <select id="sqlmChartType" class="form-select">
                            <option value="bar">Bar</option>
                            <option value="line">Line</option>
                            <option value="doughnut">Doughnut</option>
                            <option value="pie">Pie</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Label column</label>
                        <select id="sqlmChartLabelColumn" class="form-select"></select>
                    </div>
                    <div>
                        <label class="form-label">Value column</label>
                        <select id="sqlmChartValueColumn" class="form-select"></select>
                    </div>
                    <div class="d-flex align-items-end">
                        <button id="sqlmRenderChartBtn" type="button" class="btn btn-primary w-100">
                            <i class="fas fa-chart-line me-1"></i>Render chart
                        </button>
                    </div>
                </div>
                <div class="sqlm-chart-stage">
                    <canvas id="sqlmChartCanvas"></canvas>
                    <div id="sqlmChartEmpty" class="sqlm-empty d-none">Không đủ dữ liệu số để tạo chart cho bảng này.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script id="sqlmBootstrap" type="application/json"><?= json_encode($bootstrapPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
