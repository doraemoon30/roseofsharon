<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];

// --- Fetch current user info safely ---
$stmt = $conn->prepare("
    SELECT user_id, username, full_name, email, profile_photo
    FROM users
    WHERE username = ?
");
$stmt->bind_param("s", $username);
$stmt->execute();
$result  = $stmt->get_result();
$profile = $result->fetch_assoc();

// Guard against non-array or missing row
if (!is_array($profile)) {
    $profile = [
        'user_id'       => null,
        'username'      => $username,
        'full_name'     => '',
        'email'         => '',
        'profile_photo' => 'default.png'
    ];
}

// --- Resolve photo path with safe fallback ---
$photoFile = $profile['profile_photo'] ?: 'default.png';
$photoPath = 'uploads/profiles/' . $photoFile;
if (!file_exists($photoPath)) {
    $photoPath = 'uploads/profiles/default.png';
}

// --- Handle form submit ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');

    // Default to existing photo (or default.png)
    $photo = $profile['profile_photo'] ?: 'default.png';

    // Handle profile photo upload (optional)
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['profile_photo']['tmp_name'];

        // Validate type: prefer mime_content_type, fallback to extension check
        $allowedTypes = ['image/jpeg', 'image/png'];
        $fileType = function_exists('mime_content_type') ? mime_content_type($tmpPath) : null;

        $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $extAllowed = in_array($ext, ['jpg','jpeg','png'], true);

        $typeOk = ($fileType && in_array($fileType, $allowedTypes, true)) || (!$fileType && $extAllowed);

        if ($typeOk) {
            $filename  = $username . '.' . $ext;               // e.g., jeffrey.jpeg
            $targetDir = 'uploads/profiles/';
            $target    = $targetDir . $filename;

            if (!is_dir($targetDir)) { @mkdir($targetDir, 0775, true); }

            if (move_uploaded_file($tmpPath, $target)) {
                $photo = $filename;
            } else {
                if (function_exists('set_flash_message')) {
                    set_flash_message('warning', 'Could not save the uploaded image. Please try again.');
                }
                header('Location: my_profile.php'); exit();
            }
        } else {
            if (function_exists('set_flash_message')) {
                set_flash_message('warning', 'Invalid image type. Please upload a JPG or PNG file.');
            }
            header('Location: my_profile.php'); exit();
        }
    }

    // Update database
    $update = $conn->prepare("
        UPDATE users
           SET full_name = ?, email = ?, profile_photo = ?
         WHERE username = ?
    ");
    $update->bind_param("ssss", $full_name, $email, $photo, $username);
    $update->execute();

    // Keep header/profile name in session fresh
    $_SESSION['full_name'] = $full_name;

    if (function_exists('set_flash_message')) {
        set_flash_message('success', 'Profile updated successfully.');
    }
    header("Location: my_profile.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Profile</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
  <style>
    :root{
      --card-radius: 18px;
      --avatar-radius: 24px; /* rounded rectangle (not circle) */
    }
    .profile-card{
      border-radius: var(--card-radius);
      overflow: hidden;
      box-shadow: 0 10px 24px rgba(0,0,0,.12);
      background:#fff;
    }
    .profile-header{
      background: linear-gradient(90deg,#0d6efd,#2ea0ff);
      color:#fff;
      padding: 22px 24px;
    }
    .profile-header .title{
      display:flex; align-items:center; gap:.6rem; margin:0;
      font-weight:700; letter-spacing:.2px;
    }
    .profile-top{
      display:flex; gap:22px; align-items:center; padding:22px 24px 10px 24px;
    }
    .avatar-wrap{
      position:relative; width:160px; height:160px;
      border-radius: var(--avatar-radius);
      overflow:hidden; flex:0 0 160px;
      box-shadow: 0 6px 16px rgba(0,0,0,.18);
      background:#f2f2f2;
    }
    .avatar{
      width:100%; height:100%; object-fit:cover; display:block;
    }
    .avatar-overlay{
      position:absolute; inset:auto 0 0 0;
      background: rgba(0,0,0,.55); color:#fff;
      text-align:center; font-size:.95rem; padding:8px 10px;
      border-bottom-left-radius: var(--avatar-radius);
      border-bottom-right-radius: var(--avatar-radius);
      opacity:0; transition:.2s ease;
      pointer-events:none;
    }
    .avatar-wrap:hover .avatar-overlay{ opacity:1; }
    .info h4{ margin:0 0 .25rem 0; font-weight:700; }
    .info .meta{ color:#6c757d; margin:.15rem 0; }
    .divider{ height:1px; background:#eef0f2; margin:8px 24px 16px 24px; }
    .label-sm{ font-size:.9rem; color:#6c757d; }
    .form-actions{ padding:0 24px 24px 24px; }

    @media (max-width: 576px){
      .profile-top{ flex-direction:column; text-align:center; }
      .info{ width:100%; }
    }
  </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
  <?php include 'header.php'; ?>
  <?php if (function_exists('render_flashes')) { render_flashes(); } ?>

  <div class="container py-4">
    <div class="row justify-content-center">
      <div class="col-lg-8 col-xl-7">
        <div class="card profile-card">
          <div class="profile-header">
            <h5 class="title"><i class="bi bi-person-badge"></i> My Profile</h5>
          </div>

          <!-- Visible CDW details -->
          <div class="profile-top">
            <label for="profile_photo_input" class="avatar-wrap" title="Change photo">
              <img src="<?= htmlspecialchars($photoPath) ?>" class="avatar" alt="Profile Photo">
              <div class="avatar-overlay"><i class="bi bi-camera"></i> Click to change</div>
            </label>
            <input type="file" name="profile_photo" id="profile_photo_input" class="d-none" accept=".jpg,.jpeg,.png" form="profileForm">

            <div class="info">
              <h4><?= htmlspecialchars($profile['full_name'] ?: '—') ?></h4>
              <div class="meta"><i class="bi bi-person"></i> @<?= htmlspecialchars($profile['username']) ?></div>
              <div class="meta"><i class="bi bi-envelope"></i> <?= htmlspecialchars($profile['email'] ?: '—') ?></div>
            </div>
          </div>

          <div class="divider"></div>

          <!-- Edit form -->
          <form id="profileForm" method="POST" enctype="multipart/form-data" class="px-4 pb-3">
            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <input type="text" name="full_name" value="<?= htmlspecialchars($profile['full_name'] ?? '') ?>" class="form-control form-control-lg" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" class="form-control form-control-lg" required>
            </div>

            <div class="form-actions d-flex justify-content-between">
              <a href="dashboard.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Update Profile
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Clicking the photo opens the file picker
document.querySelector('label[for="profile_photo_input"]')?.addEventListener('click', () => {
  document.getElementById('profile_photo_input').click();
});
</script>
</body>
</html>
