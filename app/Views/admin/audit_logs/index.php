<?php
$logs = is_array($logs ?? null) ? $logs : [];
$meta = is_array($meta ?? null) ? $meta : ['current_page' => 1, 'last_page' => 1];
$search = (string) ($search ?? '');
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Audit Log</h1>
        <p class="text-secondary mb-0">Theo dõi lịch sử thao tác của quản trị viên, địa chỉ IP truy cập và màn hình phát sinh thay đổi.</p>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="admin-card-body">
        <form class="row g-2" method="get" action="<?= base_url('admin/audit-logs') ?>">
            <div class="col-md-8">
                <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Tìm theo admin, email, hành động, IP, đối tượng hoặc meta...">
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary">Lọc log</button>
            </div>
            <div class="col-md-2 d-grid">
                <a class="btn btn-outline-secondary" href="<?= base_url('admin/audit-logs') ?>">Xóa lọc</a>
            </div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header d-flex justify-content-between align-items-center gap-2">
        <h2 class="h6 fw-bold mb-0">Danh sách audit log</h2>
        <span class="small text-secondary">Trang <?= (int) ($meta['current_page'] ?? 1) ?> / <?= (int) ($meta['last_page'] ?? 1) ?></span>
    </div>
    <div class="admin-card-body">
        <div class="admin-table-wrap">
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Admin</th>
                            <th>Hành động</th>
                            <th>Đối tượng</th>
                            <th>IP truy cập</th>
                            <th>Màn thao tác</th>
                            <th>Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $metaJson = json_decode((string) ($log['meta_json'] ?? ''), true);
                            $requestPath = is_array($metaJson) ? (string) ($metaJson['request_path'] ?? '') : '';
                            $requestMethod = is_array($metaJson) ? strtoupper((string) ($metaJson['request_method'] ?? '')) : '';

                            if (is_array($metaJson)) {
                                unset($metaJson['request_path'], $metaJson['request_method']);
                            }
                            ?>
                            <tr>
                                <td><?= e((string) ($log['created_at'] ?? '')) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= e((string) ($log['admin_name'] ?? 'N/A')) ?></div>
                                    <div class="small text-secondary"><?= e((string) ($log['admin_email'] ?? '')) ?></div>
                                </td>
                                <td><span class="admin-badge-soft is-info"><?= e((string) ($log['action_name'] ?? '')) ?></span></td>
                                <td>
                                    <div class="fw-semibold"><?= e((string) ($log['entity_name'] ?? '')) ?></div>
                                    <div class="small text-secondary">#<?= (int) ($log['entity_id'] ?? 0) ?></div>
                                </td>
                                <td>
                                    <code><?= e((string) ($log['ip_address'] ?? '0.0.0.0')) ?></code>
                                    <div class="small text-secondary text-truncate" style="max-width: 220px;" title="<?= e((string) ($log['user_agent'] ?? '')) ?>">
                                        <?= e((string) ($log['user_agent'] ?? '')) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($requestPath !== ''): ?>
                                        <div class="fw-semibold"><?= e($requestPath) ?></div>
                                        <div class="small text-secondary"><?= e($requestMethod !== '' ? $requestMethod : 'GET') ?></div>
                                    <?php else: ?>
                                        <span class="text-secondary">Chưa có dữ liệu</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (is_array($metaJson) && !empty($metaJson)): ?>
                                        <code><?= e((string) json_encode($metaJson, JSON_UNESCAPED_UNICODE)) ?></code>
                                    <?php else: ?>
                                        <span class="text-secondary">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$logs): ?>
                            <tr><td colspan="7" class="text-center text-secondary py-4">Chưa có bản ghi audit.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ((int) ($meta['last_page'] ?? 1) > 1): ?>
            <nav class="mt-3" aria-label="Phân trang audit log">
                <ul class="pagination mb-0 flex-wrap">
                    <?php
                    $currentPage = (int) ($meta['current_page'] ?? 1);
                    $lastPage = (int) ($meta['last_page'] ?? 1);
                    $queryBase = ['q' => $search];
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($lastPage, $currentPage + 2);
                    ?>
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(base_url('admin/audit-logs?' . http_build_query(array_filter($queryBase + ['page' => max(1, $currentPage - 1)], static fn($value) => $value !== '')))) ?>">Trước</a>
                    </li>
                    <?php if ($startPage > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= e(base_url('admin/audit-logs?' . http_build_query(array_filter($queryBase + ['page' => 1], static fn($value) => $value !== '')))) ?>">1</a></li>
                        <?php if ($startPage > 2): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $currentPage === $i ? 'active' : '' ?>">
                            <a class="page-link" href="<?= e(base_url('admin/audit-logs?' . http_build_query(array_filter($queryBase + ['page' => $i], static fn($value) => $value !== '')))) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($endPage < $lastPage): ?>
                        <?php if ($endPage < ($lastPage - 1)): ?>
                            <li class="page-item disabled"><span class="page-link">…</span></li>
                        <?php endif; ?>
                        <li class="page-item"><a class="page-link" href="<?= e(base_url('admin/audit-logs?' . http_build_query(array_filter($queryBase + ['page' => $lastPage], static fn($value) => $value !== '')))) ?>"><?= $lastPage ?></a></li>
                    <?php endif; ?>
                    <li class="page-item <?= $currentPage >= $lastPage ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= e(base_url('admin/audit-logs?' . http_build_query(array_filter($queryBase + ['page' => min($lastPage, $currentPage + 1)], static fn($value) => $value !== '')))) ?>">Sau</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>
