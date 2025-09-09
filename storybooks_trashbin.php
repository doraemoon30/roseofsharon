<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['full_name'])) {
    header('Location: login.php');
    exit();
}

$trashed = $conn->query("
    SELECT id, title, description, deleted_at
    FROM storybooks
    WHERE deleted_at IS NOT NULL
    ORDER BY title ASC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Trash Bin – ROSE OF SHARON</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: url('assets/images/storytime.png') repeat;
      background-size: 50% auto;
      color: white;
    }
    .content-wrap {
      background: rgba(0,0,0,0.55);
      padding: 40px;
      border-radius: 15px;
      max-width: 1100px;
      margin: 40px auto;
    }
    h3.page-title {
      font-size: 2rem;
      font-weight: 800;
      text-transform: uppercase;
      color: white;
    }
    table { background: rgba(255,255,255,0.9); color: black; }
    .table-dark th { color: white !important; }
  </style>
</head>
<body>


<div class="main-content">
  <?php include 'header.php'; ?>


  <!-- FLASH: unified placement -->
  <?php if (function_exists('render_flashes')) { render_flashes(); } ?>

  <div class="content-wrap">
    <h3 class="page-title mb-4">♻️ ARCHIVE STORYBOOKS</h3>

    <div class="table-responsive">
      <table class="table table-bordered table-hover align-middle">
        <thead class="table-dark">
          <tr>
            <th>Title</th>
            <th>Description</th>
            <th>Deleted At</th>
            <th width="25%">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($trashed)): ?>
            <?php foreach ($trashed as $book): ?>
              <tr>
                <td><?= htmlspecialchars($book['title']) ?></td>
                <td><?= htmlspecialchars($book['description'] ?? '') ?></td>
                <td><?= htmlspecialchars($book['deleted_at']) ?></td>
                <td>
                  <a href="storybooks_restore.php?id=<?= (int)$book['id'] ?>" class="btn btn-success btn-sm">♻️ Restore</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" class="text-center text-muted">No storybooks in trash.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="mt-4">
      <a href="storybooks.php" class="btn btn-outline-light">🔙 Back to Story Bank</a>
    </div>
  </div>

  <?php include 'footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
