<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';
require_once 'helpers.php'; // ✅ get_current_year()

header('Content-Type: text/html; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Inputs
$classType    = $_GET['class_type']   ?? '';
$sessionType  = strtolower(trim($_GET['session_type'] ?? ''));
$includeIdRaw = $_GET['include_id']   ?? null;
$includeId    = is_null($includeIdRaw) ? 0 : (int)$includeIdRaw;

// Sanity check on class type
$validClasses = ['Morning Class', 'Afternoon Class'];
if (!in_array($classType, $validClasses, true)) {
  $classType = '';
}

$AY = get_current_year($conn); // ✅ current Academic Year

try {
  if ($sessionType === 'recap') {
    // Recap → list ALL active storybooks (unlimited use)
    $sql  = "SELECT id, title FROM storybooks WHERE deleted_at IS NULL ORDER BY title ASC";
    $stmt = $conn->prepare($sql);

  } elseif ($sessionType === 'assessment' && $classType !== '') {
    // Assessment → list storybooks NOT yet assessed by THIS class in THIS AY (completed only)
    if ($includeId > 0) {
      // Include the currently selected ID even if normally filtered out
      $sql = "
        SELECT sb.id, sb.title
        FROM storybooks sb
        WHERE sb.deleted_at IS NULL
          AND (
            sb.id = ? OR NOT EXISTS (
              SELECT 1
              FROM sessions s
              WHERE s.storybook_id = sb.id
                AND s.class_type    = ?
                AND s.assessed      = 1
                AND LOWER(s.notes)  = 'assessment'
                AND s.academic_year = ?
            )
          )
        ORDER BY sb.title ASC
      ";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('iss', $includeId, $classType, $AY);
    } else {
      $sql = "
        SELECT sb.id, sb.title
        FROM storybooks sb
        WHERE sb.deleted_at IS NULL
          AND NOT EXISTS (
            SELECT 1
            FROM sessions s
            WHERE s.storybook_id = sb.id
              AND s.class_type    = ?
              AND s.assessed      = 1
              AND LOWER(s.notes)  = 'assessment'
              AND s.academic_year = ?
          )
        ORDER BY sb.title ASC
      ";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param('ss', $classType, $AY);
    }

  } else {
    // Assessment but class not chosen yet → show all active (let teacher pick class first)
    $sql  = "SELECT id, title FROM storybooks WHERE deleted_at IS NULL ORDER BY title ASC";
    $stmt = $conn->prepare($sql);
  }

  $stmt->execute();
  $res = $stmt->get_result();

  if ($res && $res->num_rows) {
    while ($row = $res->fetch_assoc()) {
      echo '<option value="'.(int)$row['id'].'">'.htmlspecialchars($row['title']).'</option>' . "\n";
    }
  } else {
    echo '<option disabled>No available storybooks</option>';
  }

  $stmt->close();

} catch (Throwable $e) {
  // Fail safe: show a single error option (and log server-side if you have logs)
  echo '<option disabled>-- Error loading storybooks --</option>';
}
