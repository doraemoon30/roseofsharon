<?php
session_start();
require 'auth_check.php' ;
require_once 'flash.php'; // flash helpers

// Check login
if (!isset($_SESSION['username']) || !isset($_SESSION['full_name'])) {
    header('Location: login.php');
    exit();
}

$dcw_name = $_SESSION['full_name'];

// Load class photos
$photo_folder = "photos/";
$photo_files = array_merge(
    glob($photo_folder . "*.jpg"),
    glob($photo_folder . "*.jpeg"),
    glob($photo_folder . "*.JPG")
);
sort($photo_files);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard – ROSE OF SHARON CHILD DEVELOPMENT CENTER</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- External Libraries -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2&display=swap" rel="stylesheet">

  <!-- Custom Styles -->
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>

<div class="d-flex">
  <!-- Sidebar -->
  <?php include 'sidebar.php'; ?>

  <!-- Main Content -->
  <div class="main-content">
    <?php include 'header.php'; ?>

    <!-- FLASH: render any queued messages just under the header -->
    <?php if (function_exists('render_flashes')) { render_flashes(); } ?>

    <div class="container py-4">

      <div class="row g-4 mb-5">
        <!-- Tile 1 -->
        <div class="col-md-6">
          <div class="card text-white bg-primary h-100 text-center p-3">
            <i class="bi bi-book-half large-icon mb-2"></i>
            <h5 class="card-title">STORY BANK</h5>
            <p>Upload and manage storybooks.</p>
            <a href="storybooks.php" class="btn btn-light btn-sm mt-auto">Open Story Bank</a>
          </div>
        </div>

        <!-- Tile 2 -->
        <div class="col-md-6">
          <div class="card text-white bg-secondary h-100 text-center p-3">
            <i class="bi bi-question-circle large-icon mb-2"></i>
            <h5 class="card-title">QUESTION BANK</h5>
            <p>Manage comprehension questions.</p>
            <a href="questions.php" class="btn btn-light btn-sm mt-auto">Open Question Bank</a>
          </div>
        </div>

        <!-- Tile 3 -->
        <div class="col-md-6">
          <div class="card text-white bg-success h-100 text-center p-3">
            <i class="bi bi-play-circle large-icon mb-2"></i>
            <h5 class="card-title">SESSIONS</h5>
            <p>Launch and manage sessions.</p>
            <a href="sessions.php" class="btn btn-light btn-sm mt-auto">Open Sessions</a>
          </div>
        </div>

        <!-- Tile 4 -->
        <div class="col-md-6">
          <div class="card text-white bg-info h-100 text-center p-3">
            <i class="bi bi-bar-chart-line large-icon mb-2"></i>
            <h5 class="card-title">REPORTS</h5>
            <p>View reports and history logs.</p>
            <a href="report.php" class="btn btn-light btn-sm mt-auto">Open Reports</a>
          </div>
        </div>
      </div>

      <?php
      /* 
      // 📸 Photo Slideshow (DISABLED)
      // Note: PHP executes even inside <!-- --> comments, so use a PHP block to disable.
      if (!empty($photo_files)): ?>
        <h4 class="mb-3">📸 GALLERY : ROSE OF SHARON CHILD DEVELOPMENT CENTER</h4>
        <div id="classPhotoCarousel" class="carousel slide shadow rounded" data-bs-ride="carousel">
          <div class="carousel-inner">
            <?php foreach ($photo_files as $index => $photo): ?>
              <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                <img src="<?= $photo ?>" class="d-block w-100 rounded" alt="Class Photo" data-bs-toggle="modal" data-bs-target="#photoModal" onclick="showPhotoModal('<?= $photo ?>')">
              </div>
            <?php endforeach; ?>
          </div>
          <button class="carousel-control-prev" type="button" data-bs-target="#classPhotoCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#classPhotoCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
          </button>
        </div> 
      <?php endif; 
      */
      ?>
    </div>

    <?php include 'footer.php'; ?>
  </div>
</div>

<!-- Modal for Viewing Photos -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content bg-transparent border-0">
      <div class="modal-body p-0">
        <img src="" id="modalImage" class="img-fluid rounded shadow" alt="Class Photo">
      </div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script>
function showPhotoModal(photoSrc) {
  document.getElementById('modalImage').src = photoSrc;
}

// ⏳ Auto logout after 5 minutes of inactivity
let timeout;
function resetTimer() {
  clearTimeout(timeout);
  timeout = setTimeout(() => {
    window.location.href = 'logout.php?timeout=1';
  }, 300000); // 5 minutes
}
['mousemove', 'keydown', 'mousedown', 'touchstart'].forEach(evt => {
  document.addEventListener(evt, resetTimer, false);
});
resetTimer();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
