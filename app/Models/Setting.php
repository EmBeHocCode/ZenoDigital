<?php

namespace App\Models;

use App\Core\Model;

class Setting extends Model
{
    public function all(): array
    {
        $stmt = $this->db->query('SELECT setting_key, setting_value FROM settings');
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = $row['setting_value'];
        }

        return $result;
    }

    public function upsertMany(array $data): void
    {
        $stmt = $this->db->prepare('INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');

        foreach ($data as $key => $value) {
            $stmt->execute(['k' => $key, 'v' => $value]);
        }
    }
}
