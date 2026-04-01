<?php

class Setting {
    /**
     * Get a setting value by key. Returns default if not found.
     */
    public static function get(string $key, ?string $default = null): ?string {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    }

    /**
     * Set a setting value (insert or update).
     */
    public static function set(string $key, ?string $value): void {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);
    }

    /**
     * Get all settings as key => value array.
     */
    public static function all(): array {
        $db = getDB();
        $rows = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
        return $result;
    }

    /**
     * Check if a user has a specific permission.
     * Permissions stored as comma-separated user IDs under the key.
     */
    public static function userHasPermission(int $userId, string $permissionKey): bool {
        $value = self::get($permissionKey, '');
        if ($value === '' || $value === null) return false;
        $ids = array_map('intval', array_filter(explode(',', $value)));
        return in_array($userId, $ids, true);
    }

    /**
     * Get user IDs that have a permission.
     */
    public static function getUsersWithPermission(string $permissionKey): array {
        $value = self::get($permissionKey, '');
        if ($value === '' || $value === null) return [];
        return array_map('intval', array_filter(explode(',', $value)));
    }

    /**
     * Set user IDs for a permission (from array of user IDs).
     */
    public static function setPermissionUsers(string $permissionKey, array $userIds): void {
        $value = implode(',', array_map('intval', $userIds));
        self::set($permissionKey, $value);
    }
}
