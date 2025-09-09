<?php
require_once 'db.php';
if (!isset($_SESSION)) session_start();

$currentPage = basename($_SERVER['PHP_SELF']);
$fullName = $_SESSION['full_name'] ?? 'Guest';
$username = $_SESSION['username'] ?? '';

$photo = 'default.png';

if ($username) {
    $stmt = $conn->prepare("SELECT profile_photo FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $photo = (!empty($row['profile_photo']) && file_exists('uploads/profiles/' . $row['profile_photo']))
            ? $row['profile_photo']
            : 'default.png';
    }
}
?>

<!-- Static Sidebar for Desktop -->
<div class="sidebar">
  <!-- Profile Section -->
  <div class="profile-box text-center mb-4">
    <a href="my_profile.php">
      <img src="uploads/profiles/<?= htmlspecialchars($photo) ?>" alt="Profile">
    </a>
    <h6 class="mt-2"><?= htmlspecialchars($fullName) ?></h6>
    <a href="my_profile.php" class="btn btn-sm btn-outline-light mt-2">My Profile</a>
  </div>

 <!-- Navigation -->
<nav class="nav flex-column">
  <a href="dashboard.php" class="nav-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
    <i class="bi bi-house-door-fill me-2"></i> Dashboard
  </a>

  <a href="storybooks.php" class="nav-link <?= $currentPage == 'storybooks.php' ? 'active' : '' ?>">
    <i class="bi bi-book-half me-2"></i> Story Bank
  </a>

  <a href="questions.php" class="nav-link <?= $currentPage == 'questions.php' ? 'active' : '' ?>">
    <i class="bi bi-question-circle-fill me-2"></i> Question Bank
  </a>
  <a href="sessions.php" class="nav-link <?= $currentPage == 'sessions.php' ? 'active' : '' ?>">
    <i class="bi bi-play-circle-fill me-2"></i> Sessions
  </a>
  <a href="report.php" class="nav-link <?= $currentPage == 'report_assessment.php' ? 'active' : '' ?>">
    <i class="bi bi-bar-chart-line-fill me-2"></i> Reports
  </a>
  <a href="logout.php" class="nav-link <?= $currentPage == 'logout.php' ? 'active' : '' ?>">
    <i class="bi bi-box-arrow-right me-2"></i> Logout
  </a>
</nav>

</div>
