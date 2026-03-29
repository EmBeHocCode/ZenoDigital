<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Rank Management</h1>
        <p class="text-secondary mb-0">Thiết lập ngưỡng điểm và ưu đãi coupon tự động theo từng cấp bậc.</p>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h2 class="h6 fw-bold mb-0">Cấu hình hạng thành viên</h2>
    </div>
    <div class="admin-card-body">
        <form method="post" action="<?= base_url('admin/ranks/update') ?>" class="row g-3">
            <?= csrf_field() ?>

            <div class="col-md-6 col-xl-4">
                <div class="admin-kpi-card h-100">
                    <div class="admin-kpi-label mb-2">Common</div>
                    <label class="form-label small">Ngưỡng điểm</label>
                    <input class="form-control mb-2" type="number" value="0" readonly>
                    <label class="form-label small">Giảm giá (%)</label>
                    <input class="form-control" type="number" value="0" readonly>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="admin-kpi-card h-100">
                    <div class="admin-kpi-label mb-2">Uncommon</div>
                    <label class="form-label small">Ngưỡng điểm</label>
                    <input class="form-control mb-2" type="number" min="100" name="rank_uncommon_points" value="<?= (int) ($rankSettings['uncommon_points'] ?? 500) ?>" required>
                    <label class="form-label small">Giảm giá (%)</label>
                    <input class="form-control" type="number" min="1" max="90" name="rank_uncommon_discount" value="<?= (int) ($rankSettings['uncommon_discount'] ?? 3) ?>" required>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="admin-kpi-card h-100">
                    <div class="admin-kpi-label mb-2">Rare</div>
                    <label class="form-label small">Ngưỡng điểm</label>
                    <input class="form-control mb-2" type="number" min="100" name="rank_rare_points" value="<?= (int) ($rankSettings['rare_points'] ?? 1500) ?>" required>
                    <label class="form-label small">Giảm giá (%)</label>
                    <input class="form-control" type="number" min="1" max="90" name="rank_rare_discount" value="<?= (int) ($rankSettings['rare_discount'] ?? 5) ?>" required>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="admin-kpi-card h-100">
                    <div class="admin-kpi-label mb-2">Epic</div>
                    <label class="form-label small">Ngưỡng điểm</label>
                    <input class="form-control mb-2" type="number" min="100" name="rank_epic_points" value="<?= (int) ($rankSettings['epic_points'] ?? 3000) ?>" required>
                    <label class="form-label small">Giảm giá (%)</label>
                    <input class="form-control" type="number" min="1" max="90" name="rank_epic_discount" value="<?= (int) ($rankSettings['epic_discount'] ?? 8) ?>" required>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="admin-kpi-card h-100">
                    <div class="admin-kpi-label mb-2">Legendary</div>
                    <label class="form-label small">Ngưỡng điểm</label>
                    <input class="form-control mb-2" type="number" min="100" name="rank_legendary_points" value="<?= (int) ($rankSettings['legendary_points'] ?? 6000) ?>" required>
                    <label class="form-label small">Giảm giá (%)</label>
                    <input class="form-control" type="number" min="1" max="90" name="rank_legendary_discount" value="<?= (int) ($rankSettings['legendary_discount'] ?? 12) ?>" required>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="admin-kpi-card h-100">
                    <div class="admin-kpi-label mb-2">Mythic</div>
                    <label class="form-label small">Ngưỡng điểm</label>
                    <input class="form-control mb-2" type="number" min="100" name="rank_mythic_points" value="<?= (int) ($rankSettings['mythic_points'] ?? 10000) ?>" required>
                    <label class="form-label small">Giảm giá (%)</label>
                    <input class="form-control" type="number" min="1" max="90" name="rank_mythic_discount" value="<?= (int) ($rankSettings['mythic_discount'] ?? 18) ?>" required>
                </div>
            </div>

            <div class="col-12 d-flex justify-content-end">
                <button class="btn btn-primary">Lưu cấu hình rank</button>
            </div>
        </form>
    </div>
</div>
