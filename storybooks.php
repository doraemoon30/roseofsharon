<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['full_name'])) {
    header('Location: login.php');
    exit();
}

$dcw_name = $_SESSION['full_name'];
$photo    = $_SESSION['photo'] ?? 'default.png';

// Search keyword
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : "";

// Pagination setup
$limit  = 8;
$page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Count total storybooks (with search filter)
$count_sql    = "SELECT COUNT(*) AS total FROM storybooks WHERE deleted_at IS NULL";
$count_params = [];
$count_types  = "";

if ($search_keyword !== "") {
    $count_sql      .= " AND LOWER(title) LIKE ?";
    $count_params[] = "%" . strtolower($search_keyword) . "%";
    $count_types    .= "s";
}

$count_stmt = $conn->prepare($count_sql);
if (!empty($count_params)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$count_result       = $count_stmt->get_result()->fetch_assoc();
$total_storybooks   = (int)$count_result['total'];
$count_stmt->close();

$total_pages = (int)ceil($total_storybooks / $limit);

// Fetch storybooks with pagination + (optional) used_count
$sql    = "SELECT id, title, filename, used_count FROM storybooks WHERE deleted_at IS NULL";
$params = [];
$types  = "";

if ($search_keyword !== "") {
    $sql      .= " AND LOWER(title) LIKE ?";
    $params[]  = "%" . strtolower($search_keyword) . "%";
    $types    .= "s";
}

$sql     .= " ORDER BY title ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types   .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result     = $stmt->get_result();
$storybooks = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/**
 * Class-aware usage map:
 * For each storybook, mark which class has already completed an ASSESSMENT.
 * [storybook_id]['Morning Class'] = true
 * [storybook_id]['Afternoon Class'] = true
 */
$usageMap = [];
$uSql = "SELECT storybook_id, class_type
         FROM sessions
         WHERE assessed = 1 AND LOWER(notes) = 'assessment'";
if ($uRes = $conn->query($uSql)) {
  while ($r = $uRes->fetch_assoc()) {
    $usageMap[(int)$r['storybook_id']][$r['class_type']] = true;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Storybooks – ROSE OF SHARON DAY CARE CENTER</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
  <style>
    .video-card {
      background: #fff; padding: 15px; border-radius: 10px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 25px; transition: 0.2s;
    }
    .video-thumb { width: 100%; height: 200px; object-fit: cover; border-radius: 10px; }
    .video-title { text-align: center; font-weight: bold; margin-top: 10px; }
    .action-buttons { display: flex; justify-content: center; gap: 10px; margin-top: 8px; }
  </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
  <?php include 'header.php'; ?>

  <!-- FLASH: just under header -->
  <?php if (function_exists('render_flashes')) { render_flashes(); } ?>

  <div class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
      <div class="d-flex flex-wrap gap-2">
        <a href="storybooks_add.php" class="btn btn-success">➕ Add Storybook</a>
        <a href="storybooks_trashbin.php" class="btn btn-secondary">🗂 Archive</a>
      </div>
      <form method="GET" class="d-flex" style="max-width: 300px;">
        <input type="text" name="search" value="<?= htmlspecialchars($search_keyword) ?>" class="form-control form-control-sm" placeholder="Search title...">
        <button type="submit" class="btn btn-primary btn-sm ms-2">Search</button>
      </form>
    </div>

    <div class="row" id="videoGrid">
      <?php if (!empty($storybooks)): ?>
        <?php foreach ($storybooks as $book): ?>
          <div class="col-md-6 col-lg-4 col-xl-3 video-item" data-title="<?= strtolower($book['title']) ?>">
            <div class="video-card">
              <video class="video-thumb" muted autoplay loop>
                <source src="assets/storybooks/<?= htmlspecialchars($book['filename']) ?>" type="video/mp4">
              </video>

              <div class="video-title">
                <a href="play_video.php?file=<?= urlencode(pathinfo($book['filename'], PATHINFO_FILENAME)) ?>">
                  <?= htmlspecialchars($book['title']) ?>
                </a>
              </div>

              <!-- Class-aware Lock/Usage Status -->
              <?php
                $mUsed  = !empty($usageMap[(int)$book['id']]['Morning Class']);
                $aUsed  = !empty($usageMap[(int)$book['id']]['Afternoon Class']);
                $locked = $mUsed && $aUsed;
              ?>
              <div class="text-center mt-2">
                <?php if ($locked): ?>
                  <span class="badge bg-danger">🔒 Locked</span>
                <?php else: ?>
                  <span class="badge <?= $mUsed ? 'bg-secondary' : 'bg-success' ?>">
                    Morning <?= $mUsed ? '✓ used' : 'available' ?>
                  </span>
                  <span class="badge <?= $aUsed ? 'bg-secondary' : 'bg-success' ?>">
                    Afternoon <?= $aUsed ? '✓ used' : 'available' ?>
                  </span>
                <?php endif; ?>
              </div>

              <div class="action-buttons">
                <a href="storybooks_edit.php?id=<?= (int)$book['id'] ?>" class="btn btn-warning btn-sm">✏️ Edit</a>
                <a href="storybooks_delete.php?id=<?= (int)$book['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Move this storybook to trash?')">♻️ Archive</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="text-muted">No storybooks found.</p>
      <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center mt-4">
          <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search_keyword) ?>">Previous</a>
          </li>
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
              <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search_keyword) ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search_keyword) ?>">Next</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>

    <div class="text-center mt-4">
      <a href="dashboard.php" class="btn btn-outline-primary">🔙 Back to Dashboard</a>
    </div>
  </div>

  <?php include 'footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
