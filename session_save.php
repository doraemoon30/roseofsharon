<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';
require_once 'helpers.php'; // ✅ get_current_year / set_current_year

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (session_status() === PHP_SESSION_NONE) session_start();

// Basic required fields
if (
    empty($_POST['storybook_id']) ||
    empty($_POST['session_datetime']) ||
    empty($_POST['class_type']) ||
    empty($_POST['notes'])
) {
    set_flash_message('danger', '❌ Missing required fields.');
    header("Location: session_form.php");
    exit();
}

$storybook_id     = (int)$_POST['storybook_id'];
$session_datetime = trim($_POST['session_datetime']); // e.g., 2025-08-14T10:30
$class_type       = trim($_POST['class_type']);        // 'Morning Class' | 'Afternoon Class'
$notes            = strtolower(trim($_POST['notes'])); // 'assessment' | 'recap'
$assessed         = 0;
$AY               = get_current_year($conn);           // ✅ current Academic Year

// Normalize datetime-local (replace 'T' with space; add :00 if seconds missing)
$session_datetime = str_replace('T', ' ', $session_datetime);
if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $session_datetime)) {
    $session_datetime .= ':00';
}

// Validate session type
if (!in_array($notes, ['assessment', 'recap'], true)) {
    set_flash_message('danger', '❌ Invalid session type.');
    header("Location: session_form.php");
    exit();
}

// Validate class type
if (!in_array($class_type, ['Morning Class', 'Afternoon Class'], true)) {
    set_flash_message('danger', '❌ Invalid class type.');
    header("Location: session_form.php");
    exit();
}

// Validate storybook exists and not soft-deleted (if you use deleted_at)
$sb = $conn->prepare("
    SELECT id FROM storybooks
    WHERE id = ?
      AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
");
$sb->bind_param("i", $storybook_id);
$sb->execute();
$sbRes = $sb->get_result();
if ($sbRes->num_rows === 0) {
    $sb->close();
    set_flash_message('danger', '❌ Selected storybook is not available.');
    header("Location: session_form.php");
    exit();
}
$sb->close();

/* ======================
   📌 ASSESSMENT MODE RULES (per Academic Year; only count completed assessments)
   ====================== */
if ($notes === 'assessment') {
    // 1️⃣ Per-class restriction in THIS AY
    $checkQuery = $conn->prepare("
        SELECT COUNT(*)
        FROM sessions
        WHERE storybook_id = ?
          AND class_type   = ?
          AND LOWER(notes) = 'assessment'
          AND assessed     = 1
          AND academic_year = ?
    ");
    $checkQuery->bind_param("iss", $storybook_id, $class_type, $AY);
    $checkQuery->execute();
    $checkQuery->bind_result($existingCount);
    $checkQuery->fetch();
    $checkQuery->close();

    if ((int)$existingCount > 0) {
        set_flash_message('danger', '❌ This story has already been used by this class for an assessment session this school year.');
        header("Location: session_form.php");
        exit();
    }

    // 2️⃣ Cap total completed assessments at 2 (one per class type) IN THIS AY
    $totalAssessmentsQuery = $conn->prepare("
        SELECT COUNT(*)
        FROM sessions
        WHERE storybook_id = ?
          AND LOWER(notes) = 'assessment'
          AND assessed     = 1
          AND academic_year = ?
    ");
    $totalAssessmentsQuery->bind_param("is", $storybook_id, $AY);
    $totalAssessmentsQuery->execute();
    $totalAssessmentsQuery->bind_result($totalAssessments);
    $totalAssessmentsQuery->fetch();
    $totalAssessmentsQuery->close();

    if ((int)$totalAssessments >= 2) {
        set_flash_message('danger', '❌ This story already reached the 2-assessment limit (1 Morning + 1 Afternoon) for this school year.');
        header("Location: session_form.php");
        exit();
    }
}

/* ======================
   📌 INSERT NEW SESSION (with academic_year)
   ====================== */
$insert = $conn->prepare("
    INSERT INTO sessions (storybook_id, session_datetime, class_type, notes, academic_year, assessed)
    VALUES (?, ?, ?, ?, ?, ?)
");
$insert->bind_param("issssi", $storybook_id, $session_datetime, $class_type, $notes, $AY, $assessed);

try {
    $insert->execute();
    $insert->close();
    set_flash_message('success', '✅ New session created successfully.');
    header("Location: sessions.php");
    exit();
} catch (Throwable $e) {
    if (isset($insert) && $insert instanceof mysqli_stmt) { $insert->close(); }
    set_flash_message('danger', '❌ Failed to save session: ' . $e->getMessage());
    header("Location: session_form.php");
    exit();
}
