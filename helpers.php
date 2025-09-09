<?php
// helpers.php
// Reusable helpers for Academic Year and admin checks.

if (!function_exists('ensure_app_settings_table')) {
    function ensure_app_settings_table(mysqli $conn): void {
        // Create a simple key/value table if it doesn't exist yet
        $conn->query("
            CREATE TABLE IF NOT EXISTS app_settings (
                `key`   VARCHAR(64) PRIMARY KEY,
                `value` VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}

if (!function_exists('get_current_year')) {
    function get_current_year(mysqli $conn): string {
        ensure_app_settings_table($conn);

        $stmt = $conn->prepare("SELECT `value` FROM app_settings WHERE `key`='current_year' LIMIT 1");
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($res && !empty($res['value'])) {
            return $res['value'];
        }

        // Seed a safe default if not set yet
        $default = '2025-2026';
        $stmt = $conn->prepare("
            INSERT INTO app_settings (`key`, `value`)
            VALUES ('current_year', ?)
            ON DUPLICATE KEY UPDATE `value`=`value`
        ");
        $stmt->bind_param('s', $default);
        $stmt->execute();
        $stmt->close();
        return $default;
    }
}

if (!function_exists('set_current_year')) {
    function set_current_year(mysqli $conn, string $year): void {
        ensure_app_settings_table($conn);

        $stmt = $conn->prepare("
            INSERT INTO app_settings (`key`, `value`)
            VALUES ('current_year', ?)
            ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)
        ");
        $stmt->bind_param('s', $year);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('is_admin_safe')) {
    // Uses your system’s is_admin() if present; otherwise allows access (single-user setup).
    function is_admin_safe(): bool {
        if (function_exists('is_admin')) return is_admin();
        if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') return true;
        // Single-user fallback (your app is single-user CDW)
        return true;
    }
}
