<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';
require_once 'helpers.php'; // ✅ for get_current_year()

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['full_name'])) {
  header('Location: login.php'); exit();
}

$AY = get_current_year($conn); // ✅ current Academic Year
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Start New Session</title>
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
      font-size: 2rem; font-weight: 800; text-transform: uppercase; color: white;
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
        <h2 class="page-title m-0">📌 Create New Session</h2>
      </div>
      <div class="flex-grow-1 d-flex justify-content-center">
        <span class="badge bg-primary fs-6 px-3 py-2">School Year: <?= htmlspecialchars($AY) ?></span>
      </div>
    </div>

    <form method="POST" action="session_save.php" class="form-section">
      <!-- Class Type -->
      <label>Class:</label>
      <select name="class_type" id="class_type" class="form-select mb-3" required>
        <option value="Morning Class">Morning Class</option>
        <option value="Afternoon Class">Afternoon Class</option>
      </select>

      <!-- Session Type -->
      <label>Session Type:</label>
      <select name="notes" id="session_type" class="form-select mb-3" required>
        <option value="assessment">Assessment</option>
        <option value="recap">Recap</option>
      </select>

      <!-- Storybook Selection -->
      <label>Storybook Used:</label>
      <select name="storybook_id" id="storybook_id" class="form-select mb-3" required>
        <!-- filled via AJAX -->
        <option value="">Loading…</option>
      </select>
      <div class="form-text text-muted mb-3" style="color:#333 !important;">
        Storybook availability respects the current school year (<?= htmlspecialchars($AY) ?>).
      </div>

      <!-- Date & Time -->
      <label>Date & Time of Session:</label>
      <input type="datetime-local" name="session_datetime" id="session_datetime" class="form-control mb-3" required>

      <!-- Buttons -->
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">🚀 Create Session</button>
        <a href="sessions.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>

  <?php include 'footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
(function(){
  function loadStorybooks() {
    const classType   = document.getElementById('class_type').value;
    const sessionType = document.getElementById('session_type').value;

    $.get('storybooks_filter.php', { class_type: classType, session_type: sessionType }, function (data) {
      $('#storybook_id').html(data);
    }).fail(function (xhr) {
      console.error(xhr.responseText || 'Failed to load storybooks.');
      $('#storybook_id').html('<option value="">-- Error loading storybooks --</option>');
      alert('Failed to load storybooks. Please try again.');
    });
  }

  // initial load + on change
  $('#class_type, #session_type').on('change', loadStorybooks);
  loadStorybooks();

  // Set default session_datetime to now (rounded to minutes)
  const dt = document.getElementById('session_datetime');
  if (dt && !dt.value) {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    dt.value = now.toISOString().slice(0,16);
  }
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
