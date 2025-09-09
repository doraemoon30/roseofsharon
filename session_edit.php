<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';
require_once 'helpers.php'; // ✅ get_current_year()

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Get session details
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    flash('warning', '❌ Missing session ID.');
    header('Location: sessions.php'); exit();
}

$stmt = $conn->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result  = $stmt->get_result();
$session = $result->fetch_assoc();
$stmt->close();

if (!$session) {
    flash('danger', '❌ Session not found.');
    header('Location: sessions.php'); exit();
}

$AY = get_current_year($conn); // ✅ current Academic Year
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Session</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: url('assets/images/storytime.png') repeat;
      background-size: 50% auto;
      font-family: 'Segoe UI', sans-serif;
      color: white;
    }
    .container-custom {
      background: rgba(0,0,0,0.55);
      padding: 40px;
      border-radius: 15px;
      max-width: 800px;
      margin: 40px auto;
    }
    h2.page-title {
      font-size: 2rem;
      font-weight: 800;
      text-transform: uppercase;
      color: white;
    }
    label { font-weight: 500; color: black; }
    .form-section { background: rgba(255,255,255,0.95); padding: 25px; border-radius: 10px; }
  </style>
</head>
<body>

<div class="main-content">
  <?php include 'header.php'; ?>

  <!-- FLASH: unified placement -->
  <?php if (function_exists('render_flashes')) { render_flashes(); } ?>

  <div class="container-custom">

    <!-- One-line top bar -->
    <div class="d-flex align-items-center gap-2 mb-3">
      <div class="flex-grow-0">
        <h2 class="page-title m-0">✏️ Edit Session</h2>
      </div>
      <div class="flex-grow-1 d-flex justify-content-center">
        <span class="badge bg-primary fs-6 px-3 py-2">Academic Year: <?= htmlspecialchars($AY) ?></span>
      </div>
    </div>

    <form method="POST" action="session_update.php" class="form-section">
      <input type="hidden" name="id" value="<?= (int)$session['id'] ?>">

      <!-- Class Type -->
      <label>Class:</label>
      <select name="class_type" id="class_type" class="form-select mb-3" required>
        <option value="Morning Class"   <?= $session['class_type'] === 'Morning Class'   ? 'selected' : '' ?>>Morning Class</option>
        <option value="Afternoon Class" <?= $session['class_type'] === 'Afternoon Class' ? 'selected' : '' ?>>Afternoon Class</option>
      </select>

      <!-- Session Type -->
      <label>Session Type:</label>
      <select name="notes" id="session_type" class="form-select mb-3" required>
        <option value="assessment" <?= strtolower($session['notes']) === 'assessment' ? 'selected' : '' ?>>Assessment</option>
        <option value="recap"      <?= strtolower($session['notes']) === 'recap'      ? 'selected' : '' ?>>Recap</option>
      </select>

      <!-- Storybook Selection -->
      <label>Storybook Used:</label>
      <select name="storybook_id" id="storybook_id" class="form-select mb-3" required>
        <!-- Will be loaded dynamically -->
      </select>
      <div class="form-text text-muted mb-3" style="color:#333 !important;">
        Availability reflects the selected session type and the current school year (<?= htmlspecialchars($AY) ?>).
      </div>

      <!-- Date & Time -->
      <label>Date & Time:</label>
      <input type="datetime-local" name="session_datetime"
             value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($session['session_datetime']))) ?>"
             class="form-control mb-3" required>

      <!-- Buttons -->
      <div class="mt-4 d-flex justify-content-between">
        <a href="sessions.php" class="btn btn-outline-secondary">← Back to Sessions</a>
        <button type="submit" class="btn btn-primary">💾 Update Session</button>
      </div>
    </form>
  </div>

  <?php include 'footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function loadStorybooks(selectedId = null) {
  const classType   = document.getElementById('class_type').value;
  const sessionType = document.getElementById('session_type').value;

  // ✅ include_id tells storybooks_filter.php to include this storybook even if normally filtered out
  $.get('storybooks_filter.php', {
      class_type: classType,
      session_type: sessionType,
      include_id: selectedId || <?= json_encode((int)$session['storybook_id']) ?>
    }, function (data) {
      $('#storybook_id').html(data);
      if (selectedId) $('#storybook_id').val(selectedId);
      else $('#storybook_id').val(<?= json_encode((int)$session['storybook_id']) ?>);
    }
  ).fail(function(xhr){
    console.error(xhr.responseText || 'Failed to load storybooks.');
    alert('Failed to load storybooks. Please try again.');
  });
}

// Initial load with the current storybook selected
loadStorybooks(<?= json_encode((int)$session['storybook_id']) ?>);

// Reload list when class type or session type changes
$('#class_type, #session_type').on('change', function() {
  loadStorybooks();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
