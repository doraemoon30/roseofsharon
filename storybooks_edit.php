<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash('warning','Storybook ID is missing.');
  header('Location: storybooks.php'); exit;
}

// Load storybook (skip items in trash)
$stmt = $conn->prepare("SELECT id, title, description, filename FROM storybooks WHERE id = ? AND (deleted_at IS NULL)");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
  $stmt->close();
  flash('warning','Storybook not found or is archived.');
  header('Location: storybooks.php'); exit;
}
$storybook = $res->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title'] ?? '');
  $description = trim($_POST['description'] ?? '');

  if ($title === '') {
    flash('warning','Title is required.');
    header("Location: storybooks_edit.php?id=".$id); exit;
  }

  $newFilename = null;
  $replacing = isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK;

  if ($replacing) {
    // Validate new video
    $f = $_FILES['video'];
    $maxBytes = 80 * 1024 * 1024; // 80MB
    if ($f['size'] > $maxBytes) {
      flash('warning','Video too large. Max size is 80MB.');
      header("Location: storybooks_edit.php?id=".$id); exit;
    }
    $mime = mime_content_type($f['tmp_name']);
    if ($mime !== 'video/mp4' && $mime !== 'application/octet-stream') {
      flash('warning','Invalid format. Only MP4 is allowed.');
      header("Location: storybooks_edit.php?id=".$id); exit;
    }

    // Ensure destination dir
    $destDir = __DIR__.'/assets/storybooks';
    if (!is_dir($destDir)) { @mkdir($destDir,0775,true); }

    // Safe unique name
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i','-', $title));
    $slug = trim($slug,'-') ?: 'story';
    $newFilename = time().'_'.$slug.'.mp4';
    $target = $destDir.'/'.$newFilename;

    if (!move_uploaded_file($f['tmp_name'], $target)) {
      flash('danger','❌ Upload failed. Please try again.');
      header("Location: storybooks_edit.php?id=".$id); exit;
    }
  }

  if ($replacing) {
    // Update including filename
    $upd = $conn->prepare("UPDATE storybooks SET title=?, description=?, filename=? WHERE id=? AND deleted_at IS NULL");
    $upd->bind_param('sssi', $title, $description, $newFilename, $id);
  } else {
    // Update text only
    $upd = $conn->prepare("UPDATE storybooks SET title=?, description=? WHERE id=? AND deleted_at IS NULL");
    $upd->bind_param('ssi', $title, $description, $id);
  }

  if ($upd->execute() && $upd->affected_rows >= 0) {
    // If we replaced the file, remove the old one (best-effort)
    if ($replacing && !empty($storybook['filename'])) {
      $oldPath = __DIR__.'/assets/storybooks/'.$storybook['filename'];
      if (is_file($oldPath)) @unlink($oldPath);
      $storybook['filename'] = $newFilename; // keep preview consistent if you stay on page
    }
    flash('success','✏️ Storybook updated successfully!');
    header('Location: storybooks.php'); exit;
  } else {
    // If DB failed and we had uploaded a new file, clean it up
    if ($replacing && isset($target) && is_file($target)) { @unlink($target); }
    flash('danger','❌ Update failed. Please try again.');
    header("Location: storybooks_edit.php?id=".$id); exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Storybook – ROSE OF SHARON DAY CARE CENTER</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
  <style>
    .form-container {
      max-width: 700px; margin: auto; background:#fff; padding:30px;
      border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,0.1);
    }
    textarea { resize:none; }
    h4 { font-weight:bold; margin-bottom:25px; }
  </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
  <?php include 'header.php'; ?>

  <div class="container py-5">
    <div class="form-container">
      <h4>✏️ Edit Storybook</h4>

      <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
          <label for="title" class="form-label">Storybook Title</label>
          <input type="text" class="form-control" name="title" id="title" value="<?= htmlspecialchars($storybook['title']) ?>" required>
        </div>

        <div class="mb-3">
          <label for="description" class="form-label">Description (optional)</label>
          <textarea name="description" id="description" class="form-control" rows="3"><?= htmlspecialchars($storybook['description']) ?></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Current Video</label>
          <video class="w-100 rounded border" controls>
            <source src="assets/storybooks/<?= htmlspecialchars($storybook['filename']) ?>" type="video/mp4">
          </video>
        </div>

        <div class="mb-3">
          <label for="video" class="form-label">Replace Video File (optional)</label>
          <input type="file" name="video" id="video" class="form-control" accept="video/mp4">
        </div>

        <div class="d-flex justify-content-between">
          <a href="storybooks.php" class="btn btn-secondary">← Cancel</a>
          <button type="submit" class="btn btn-primary">Update Storybook</button>
        </div>
      </form>
    </div>
  </div>

  <?php include 'footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
