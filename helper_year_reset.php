<?php
// helper_year_reset.php
require 'db.php';
require_once 'flash.php';
require_once 'helpers.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (session_status() === PHP_SESSION_NONE) session_start();

// CSRF check if provided
$postedToken = $_POST['csrf_token'] ?? null;
if ($postedToken !== null) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $postedToken)) {
        flash('danger', 'Invalid CSRF token.');
        header('Location: sessions.php'); exit;
    }
}

$nextYear   = trim($_POST['next_year'] ?? '');
$doSnapshot = isset($_POST['do_snapshot']);

if (!$nextYear || !preg_match('/^\d{4}-\d{4}$/', $nextYear)) {
    flash('warning', 'Please provide a valid Academic Year format, e.g., 2026-2027.');
    header('Location: sessions.php'); exit;
}

$conn->begin_transaction();

try {
    $currentYear = get_current_year($conn);

    if ($doSnapshot) {
        // Ensure snapshot table exists
        $conn->query("
            CREATE TABLE IF NOT EXISTS storybook_usage_yearly (
              id INT AUTO_INCREMENT PRIMARY KEY,
              academic_year     VARCHAR(9) NOT NULL,
              storybook_id      INT NOT NULL,
              used_morning      INT NOT NULL DEFAULT 0,
              used_afternoon    INT NOT NULL DEFAULT 0,
              total_assessments INT NOT NULL DEFAULT 0,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Insert aggregated usage for the closing year
        $sql = "
            INSERT INTO storybook_usage_yearly (academic_year, storybook_id, used_morning, used_afternoon, total_assessments)
            SELECT
              ?, sb.id,
              SUM(CASE WHEN s.class_type='Morning'   AND s.assessed=1 AND LOWER(s.notes)='assessment' THEN 1 ELSE 0 END) AS used_morning,
              SUM(CASE WHEN s.class_type='Afternoon' AND s.assessed=1 AND LOWER(s.notes)='assessment' THEN 1 ELSE 0 END) AS used_afternoon,
              SUM(CASE WHEN s.assessed=1 AND LOWER(s.notes)='assessment' THEN 1 ELSE 0 END) AS total_assessments
            FROM storybooks sb
            LEFT JOIN sessions s ON s.storybook_id = sb.id
              AND s.academic_year = ?
            GROUP BY sb.id
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $currentYear, $currentYear);
        $stmt->execute();
        $stmt->close();
    }

    // Switch to new year
    set_current_year($conn, $nextYear);

    // Reset counters only if you still reference storybooks.used_count anywhere.
    $conn->query("UPDATE storybooks SET used_count = 0");

    $conn->commit();
    flash('success', "New school year started: {$nextYear}. Previous year ({$currentYear}) archived. Reports remain accessible per year.");
    header('Location: sessions.php'); exit;

} catch (Throwable $e) {
    $conn->rollback();
    flash('danger', 'Year reset failed: '.$e->getMessage());
    header('Location: sessions.php'); exit;
}
