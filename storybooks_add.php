<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['full_name'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $video       = $_FILES['video'] ?? null;

    // Basic checks
    if ($title === '') {
        flash('warning', 'Title is required.');
        header('Location: storybooks_add.php'); exit;
    }
    if (!$video || $video['error'] !== UPLOAD_ERR_OK) {
        flash('danger', '❌ Invalid or missing video file.');
        header('Location: storybooks_add.php'); exit;
    }

    // Validate size (<= 80MB)
    $maxBytes = 80 * 1024 * 1024; // 80MB
    if ((int)$video['size'] > $maxBytes) {
        flash('warning', 'Video too large. Max size is 80MB.');
        header('Location: storybooks_add.php'); exit;
    }

    // Validate extension is .mp4 (case-insensitive)
    $origExt = strtolower(pathinfo($video['name'], PATHINFO_EXTENSION));
    if ($origExt !== 'mp4') {
        flash('warning', 'Invalid format. Only MP4 is allowed.');
        header('Location: storybooks_add.php'); exit;
    }

    // MIME check (allow some servers reporting octet-stream)
    $mime = @mime_content_type($video['tmp_name']) ?: '';
    if ($mime !== 'video/mp4' && $mime !== 'application/octet-stream') {
        flash('warning', 'Invalid format. Only MP4 is allowed.');
        header('Location: storybooks_add.php'); exit;
    }

    // Ensure destination dir exists
    $destDir = __DIR__ . '/assets/storybooks';
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0775, true);
    }

    // Safe unique filename: timestamp + slug + .mp4
    $slug     = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
    $slug     = trim($slug, '-');
    $filename = time() . '_' . ($slug ?: 'story') . '.mp4';
    $target   = $destDir . '/' . $filename;

    if (!move_uploaded_file($video['tmp_name'], $target)) {
        flash('danger', '❌ Upload failed. Please try again.');
        header('Location: storybooks_add.php'); exit;
    }

    // Insert DB row
    $stmt = $conn->prepare("INSERT INTO storybooks (title, filename, description) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $title, $filename, $description);

    if ($stmt->execute()) {
        flash('success', '✅ Storybook uploaded successfully!');
        header('Location: storybooks.php'); exit;
    } else {
        // Cleanup file if DB insert fails
        @unlink($target);
        flash('danger', '❌ Database error while saving storybook.');
        header('Location: storybooks_add.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Storybook – ROSE OF SHARON DAY CARE CENTER</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
  <style>
    .form-container {
      max-width: 700px; margin: auto; background: #fff; padding: 30px;
      border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    }
    textarea { resize: none; }
    h4 { font-weight: bold; margin-bottom: 25px; }
  </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
  <?php include 'header.php'; ?>

  <!-- FLASH: show messages under header -->
  <?php if (function_exists('render_flashes')) { render_flashes(); } ?>

  <div class="container py-5">
    <div class="form-container">
      <h4>➕ Add New Storybook</h4>

      <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
          <label for="title" class="form-label">Storybook Title</label>
          <input type="text" name="title" id="title" class="form-control" required autofocus>
        </div>

        <div class="mb-3">
          <label for="description" class="form-label">Description (optional)</label>
          <textarea name="description" id="description" class="form-control" rows="3" placeholder="Brief description of the story..."></textarea>
        </div>

        <div class="mb-3">
          <label for="video" class="form-label">Video File (MP4)</label>
          <input type="file" name="video" id="video" class="form-control" accept="video/mp4,.mp4" required>
        </div>

        <div class="d-flex justify-content-between">
          <a href="storybooks.php" class="btn btn-secondary">← Cancel</a>
          <button type="submit" class="btn btn-success">Upload Storybook</button>
        </div>
      </form>
    </div>
  </div>

  <?php include 'footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
