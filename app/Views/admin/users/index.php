<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Quản lý người dùng</h1>
        <p class="text-secondary mb-0">Quản trị tài khoản, vai trò và trạng thái truy cập hệ thống.</p>
    </div>
    <a href="<?= base_url('admin/users/create') ?>" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i>Thêm người dùng</a>
</div>

<div class="alert alert-info border-0 shadow-sm">
    Mật khẩu người dùng trong database được lưu dưới dạng hash nên không thể đọc lại từ SQL. Từ màn này, quản trị viên có thể đổi mật khẩu trong form sửa hoặc tạo mật khẩu tạm mới bằng nút <strong>Reset MK</strong>.
</div>

<div class="admin-card mb-3">
    <div class="admin-card-body">
        <form class="row g-2" method="get">
            <div class="col-md-4"><input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Tìm người dùng..."></div>
            <div class="col-md-3"><select class="form-select" name="role"><option value="">Tất cả vai trò</option><?php foreach ($roles as $item): ?><option value="<?= e((string) $item['name']) ?>" <?= $role === $item['name'] ? 'selected' : '' ?>><?= e((string) $item['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Lọc</button></div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header"><h2 class="h6 fw-bold mb-0">Danh sách người dùng</h2></div>
    <div class="admin-card-body p-0">
        <div class="admin-table-wrap">
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead><tr><th>ID</th><th>Họ tên</th><th>Email</th><th>Username</th><th>Mật khẩu</th><th>Giới tính</th><th>Ngày sinh</th><th>Vai trò</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>#<?= (int) $user['id'] ?></td>
                                <td class="fw-semibold"><?= e((string) $user['full_name']) ?></td>
                                <td><?= e((string) $user['email']) ?></td>
                                <td><?= e((string) $user['username']) ?></td>
                                <td>
                                    <div class="fw-semibold">Đã mã hóa</div>
                                    <div class="small text-secondary">Dùng Sửa hoặc Reset MK để cấp mật khẩu mới</div>
                                </td>
                                <td><?= e(user_gender_label((string) ($user['gender'] ?? 'unknown'))) ?></td>
                                <td><?= !empty($user['birth_date']) ? e(date('d/m/Y', strtotime((string) $user['birth_date']))) : '<span class="text-secondary">Chưa cập nhật</span>' ?></td>
                                <td><span class="admin-badge-soft is-info"><?= e((string) $user['role_name']) ?></span></td>
                                <td><span class="admin-badge-soft <?= ((string) $user['status'] === 'active') ? 'is-success' : 'is-danger' ?>"><?= e((string) $user['status']) ?></span></td>
                                <td>
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="<?= base_url('admin/users/edit/' . (int) $user['id']) ?>" class="btn btn-sm btn-outline-primary">Sửa</a>
                                        <form method="post" action="<?= base_url('admin/users/reset-password/' . (int) $user['id']) ?>" onsubmit="return confirm('Tạo mật khẩu tạm mới cho tài khoản này?')">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-secondary">Reset MK</button>
                                        </form>
                                        <form method="post" action="<?= base_url('admin/users/toggle-status/' . (int) $user['id']) ?>">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-warning">Khóa/Mở</button>
                                        </form>
                                        <form method="post" action="<?= base_url('admin/users/delete/' . (int) $user['id']) ?>" onsubmit="return confirm('Xác nhận xóa user?')">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger">Xóa</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$users): ?><tr><td colspan="10" class="text-center py-4 text-secondary">Không có dữ liệu.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($meta['last_page'] > 1): ?>
    <nav class="mt-3"><ul class="pagination mb-0">
        <?php for ($i = 1; $i <= $meta['last_page']; $i++): ?>
            <li class="page-item <?= $meta['current_page'] === $i ? 'active' : '' ?>"><a class="page-link" href="<?= base_url('admin/users?' . http_build_query(['q' => $search, 'role' => $role, 'page' => $i])) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul></nav>
<?php endif; ?>
