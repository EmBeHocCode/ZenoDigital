<?php

namespace App\Controllers\Admin;

use App\Core\Controller;

class RankController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();

        $rankSettings = [
            'common_points' => 0,
            'common_discount' => 0,
            'uncommon_points' => (int) app_setting('rank_uncommon_points', (int) app_setting('rank_silver_points', 500)),
            'rare_points' => (int) app_setting('rank_rare_points', (int) app_setting('rank_gold_points', 1500)),
            'epic_points' => (int) app_setting('rank_epic_points', (int) app_setting('rank_platinum_points', 3000)),
            'legendary_points' => (int) app_setting('rank_legendary_points', (int) app_setting('rank_diamond_points', 6000)),
            'mythic_points' => (int) app_setting('rank_mythic_points', 10000),
            'uncommon_discount' => (int) app_setting('rank_uncommon_discount', (int) app_setting('rank_silver_discount', 3)),
            'rare_discount' => (int) app_setting('rank_rare_discount', (int) app_setting('rank_gold_discount', 5)),
            'epic_discount' => (int) app_setting('rank_epic_discount', (int) app_setting('rank_platinum_discount', 8)),
            'legendary_discount' => (int) app_setting('rank_legendary_discount', (int) app_setting('rank_diamond_discount', 12)),
            'mythic_discount' => (int) app_setting('rank_mythic_discount', 18),
        ];

        $this->view('admin/ranks/index', ['rankSettings' => $rankSettings], 'admin');
    }

    public function update(): void
    {
        $this->requireAdmin();
        $this->requirePostWithCsrf('admin/ranks');

        $payload = [
            'rank_uncommon_points' => validate_int_range($_POST['rank_uncommon_points'] ?? null, 100, 1000000, 500),
            'rank_rare_points' => validate_int_range($_POST['rank_rare_points'] ?? null, 100, 1000000, 1500),
            'rank_epic_points' => validate_int_range($_POST['rank_epic_points'] ?? null, 100, 1000000, 3000),
            'rank_legendary_points' => validate_int_range($_POST['rank_legendary_points'] ?? null, 100, 1000000, 6000),
            'rank_mythic_points' => validate_int_range($_POST['rank_mythic_points'] ?? null, 100, 1000000, 10000),
            'rank_uncommon_discount' => validate_int_range($_POST['rank_uncommon_discount'] ?? null, 1, 90, 3),
            'rank_rare_discount' => validate_int_range($_POST['rank_rare_discount'] ?? null, 1, 90, 5),
            'rank_epic_discount' => validate_int_range($_POST['rank_epic_discount'] ?? null, 1, 90, 8),
            'rank_legendary_discount' => validate_int_range($_POST['rank_legendary_discount'] ?? null, 1, 90, 12),
            'rank_mythic_discount' => validate_int_range($_POST['rank_mythic_discount'] ?? null, 1, 90, 18),
        ];

        if (!($payload['rank_uncommon_points'] < $payload['rank_rare_points']
            && $payload['rank_rare_points'] < $payload['rank_epic_points']
            && $payload['rank_epic_points'] < $payload['rank_legendary_points']
            && $payload['rank_legendary_points'] < $payload['rank_mythic_points'])) {
            flash('danger', 'Ngưỡng điểm rank phải tăng dần từ Uncommon -> Rare -> Epic -> Legendary -> Mythic.');
            redirect('admin/ranks');
        }

        // Added: keep legacy Silver/Gold/Platinum/Diamond keys in sync for backward compatibility.
        $legacyPayload = [
            'rank_silver_points' => (string) $payload['rank_uncommon_points'],
            'rank_gold_points' => (string) $payload['rank_rare_points'],
            'rank_platinum_points' => (string) $payload['rank_epic_points'],
            'rank_diamond_points' => (string) $payload['rank_legendary_points'],
            'rank_silver_discount' => (string) $payload['rank_uncommon_discount'],
            'rank_gold_discount' => (string) $payload['rank_rare_discount'],
            'rank_platinum_discount' => (string) $payload['rank_epic_discount'],
            'rank_diamond_discount' => (string) $payload['rank_legendary_discount'],
        ];

        $settingModel = new \App\Models\Setting($this->config);
        $settingModel->upsertMany(array_merge(
            array_map(static fn($value) => (string) $value, $payload),
            $legacyPayload
        ));
        app_settings(true);
        admin_audit('update', 'rank_settings', null, $payload);

        flash('success', 'Đã cập nhật cấu hình rank thành công.');
        redirect('admin/ranks');
    }
}
