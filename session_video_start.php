<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';
require_once 'helpers.php'; // ✅ get_current_year()

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (session_status() === PHP_SESSION_NONE) session_start();

$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
if ($session_id <= 0) {
    flash('warning', '⚠️ Missing session ID.');
    header('Location: sessions.php'); exit();
}

$AY_current = get_current_year($conn);

// Fetch session meta: type (notes), storybook_id, class_type, AY
$type = 'assessment';
$storybook_id = null;
$class_type   = '';
$AY_session   = null;

$metaStmt = $conn->prepare("SELECT notes, storybook_id, class_type, academic_year FROM sessions WHERE id = ?");
$metaStmt->bind_param("i", $session_id);
$metaStmt->execute();
$metaRes = $metaStmt->get_result();
if ($row = $metaRes->fetch_assoc()) {
    $type         = (strtolower(trim($row['notes'])) === 'recap') ? 'recap' : 'assessment';
    $storybook_id = (int)$row['storybook_id'];
    $class_type   = (string)$row['class_type'];
    $AY_session   = (string)($row['academic_year'] ?? '');
}
$metaStmt->close();

if (!$storybook_id) {
    flash('danger', '🚫 Session is missing a storybook.');
    header('Location: sessions.php'); exit();
}

// ✅ Class-aware lock: only for ASSESSMENT, per class, PER CURRENT ACADEMIC YEAR
if ($type === 'assessment') {
    $chk = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM sessions
        WHERE storybook_id = ?
          AND class_type   = ?
          AND assessed     = 1
          AND LOWER(notes) = 'assessment'
          AND academic_year = ?
    ");
    $chk->bind_param("iss", $storybook_id, $class_type, $AY_current);
    $chk->execute();
    $c = (int)$chk->get_result()->fetch_assoc()['c'];
    $chk->close();

    if ($c >= 1) {
        // Block launching another assessment for the same class in THIS AY
        flash('danger', '🚫 This storybook has already been assessed by <b>'.htmlspecialchars($class_type).'</b> in <b>'.htmlspecialchars($AY_current).'</b>.');
        header('Location: sessions.php'); exit();
    }
}

// ✅ Reset/initialize student_view_state
$initStmt = $conn->prepare("
  INSERT INTO student_view_state (session_id, current_q_index, feedback)
  VALUES (?, 0, 'none')
  ON DUPLICATE KEY UPDATE current_q_index = 0, feedback = 'none'
");
$initStmt->bind_param("i", $session_id);
if (!$initStmt->execute()) {
    flash('danger', 'Failed to initialize student view.');
    header('Location: sessions.php'); exit();
}
$initStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Launching Session</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f8f9fa; font-family:'Segoe UI', sans-serif; }
  </style>
</head>
<body class="d-flex flex-column justify-content-center align-items-center vh-100">
  <h2 class="mb-2">Ready to Launch <?= htmlspecialchars(ucfirst($type)) ?> Session?</h2>

  <p class="text-muted mb-1">
    Academic Year: <span class="badge bg-primary"><?= htmlspecialchars($AY_current) ?></span>
    <?php if ($AY_session && $AY_session !== $AY_current): ?>
      <br><small class="text-danger">Note: This session was created under <b><?= htmlspecialchars($AY_session) ?></b>.</small>
    <?php endif; ?>
  </p>

  <p class="text-muted mb-4">
    <?php if ($type === 'recap'): ?>
      This will open the student view for recap only — no scores will be recorded.
    <?php else: ?>
      This will open the student view in a separate window and start the assessment.
    <?php endif; ?>
  </p>

  <div class="d-flex gap-2">
    <a href="sessions.php" class="btn btn-outline-secondary">← Back</a>
    <button class="btn btn-success btn-lg" onclick="launchSession()">🚀 Launch Now</button>
  </div>

  <script>
  function launchSession() {
    const sessionId = <?= json_encode($session_id) ?>;
    // Open Student View
    window.open(
      'student_view.php?session_id=' + sessionId,
      'StudentViewWindow',
      'width=1280,height=720,left=100,top=50'
    );
    // Redirect teacher view to assessment controller (auto-detects recap/assessment)
    window.location.href = 'assess.php?session_id=' + sessionId;
  }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
