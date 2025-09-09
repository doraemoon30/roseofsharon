<?php
if (!isset($_SESSION)) session_start();
require_once 'db.php';
require_once 'auth_check.php';
require_once 'flash.php';

$dcw_name = $_SESSION['full_name'] ?? 'Daycare Worker';
$username = $_SESSION['username'] ?? '';

$photo = 'default.png';
if ($username) {
    // 🟢 Use unique variable names to avoid clobbering page variables
    $hdrStmt = $conn->prepare("SELECT profile_photo FROM users WHERE username = ?");
    $hdrStmt->bind_param("s", $username);
    $hdrStmt->execute();
    $hdrRes = $hdrStmt->get_result();
    if ($hdrRow = $hdrRes->fetch_assoc()) {
        $candidate = $hdrRow['profile_photo'] ?? '';
        $photo = (!empty($candidate) && file_exists('uploads/profiles/' . $candidate))
            ? $candidate
            : 'default.png';
    }
    $hdrStmt->close();
}
?>

<nav class="navbar navbar-expand px-4 py-3 shadow-sm" style="background-color: #1DCD9F; position: relative; z-index: 1050;">
  <div class="container-fluid d-flex justify-content-between align-items-center">

    <!-- System Title -->
    <span class="navbar-brand fw-bold fs-5 text-dark">
      LISTEN IT ANSWER IT - ROSE OF SHARON CHILD DEVELOPMENT CENTER
    </span>

    <!-- User Profile Dropdown -->
    <div class="dropdown">
      <button class="btn btn-light dropdown-toggle fw-semibold d-flex align-items-center" 
              type="button" id="profileDropdown" 
              data-bs-toggle="dropdown" aria-expanded="false">
        <img src="uploads/profiles/<?= htmlspecialchars($photo) ?>" 
             alt="Profile" 
             style="width:32px; height:32px; border-radius:50%; object-fit:cover; margin-right:8px;">
        <?= htmlspecialchars($dcw_name) ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="profileDropdown">
        <li><a class="dropdown-item" href="my_profile.php"><i class="bi bi-person-fill me-2"></i> My Profile</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
      </ul>
    </div>

  </div>
</nav>

<style>
  .dropdown-menu { z-index: 2000 !important; }
</style>
