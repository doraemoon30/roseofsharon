<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['full_name'])) {
  header('Location: login.php'); exit();
}

$dcw_name = $_SESSION['full_name'];

// Pagination for storybooks list
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit  = 10;
$offset = ($page - 1) * $limit;

// Filters
$selected_storybook = isset($_GET['storybook_id']) ? (int)$_GET['storybook_id'] : 0;
$bookSearch         = trim($_GET['book_search'] ?? ''); // NEW: search by storybook title

// ---------- Total active storybooks (with optional title search) ----------
if ($bookSearch !== '') {
  $countSql  = "SELECT COUNT(*) AS total FROM storybooks WHERE deleted_at IS NULL AND title LIKE ?";
  $countStmt = $conn->prepare($countSql);
  $like      = "%{$bookSearch}%";
  $countStmt->bind_param('s', $like);
  $countStmt->execute();
  $countRes  = $countStmt->get_result();
  $totalCount = $countRes ? (int)$countRes->fetch_assoc()['total'] : 0;
  $countStmt->close();
} else {
  $countRes = $conn->query("SELECT COUNT(*) AS total FROM storybooks WHERE deleted_at IS NULL");
  $totalCount = $countRes ? (int)$countRes->fetch_assoc()['total'] : 0;
}
$totalPages = max(1, (int)ceil($totalCount / $limit));

// ---------- Fetch active storybooks (paginated, filtered by title if provided) ----------
$storybooks = [];
if ($bookSearch !== '') {
  $booksSql  = "SELECT id, title FROM storybooks
                WHERE deleted_at IS NULL AND title LIKE ?
                ORDER BY title ASC
                LIMIT ? OFFSET ?";
  $booksStmt = $conn->prepare($booksSql);
  $booksStmt->bind_param('sii', $like, $limit, $offset);
  $booksStmt->execute();
  $resBooks = $booksStmt->get_result();
  if ($resBooks) $storybooks = $resBooks->fetch_all(MYSQLI_ASSOC);
  $booksStmt->close();
} else {
  $booksSql = "SELECT id, title FROM storybooks
               WHERE deleted_at IS NULL
               ORDER BY title ASC
               LIMIT ? OFFSET ?";
  $booksStmt = $conn->prepare($booksSql);
  $booksStmt->bind_param('ii', $limit, $offset);
  $booksStmt->execute();
  $resBooks = $booksStmt->get_result();
  if ($resBooks) $storybooks = $resBooks->fetch_all(MYSQLI_ASSOC);
  $booksStmt->close();
}

// ---------- Preload all active books for dropdown + title lookup ----------
$allBooks = [];
$allBooksRes = $conn->query("SELECT id, title FROM storybooks WHERE deleted_at IS NULL ORDER BY title ASC");
if ($allBooksRes) $allBooks = $allBooksRes->fetch_all(MYSQLI_ASSOC);

// ---------- Fetch questions for selected book (no keyword search anymore) ----------
$questions = [];
if ($selected_storybook > 0) {
  $sql = "SELECT id, storybook_id, question_text, correct_keywords, language, question_set
          FROM questions
          WHERE storybook_id = ?
          ORDER BY id DESC";
  $qStmt = $conn->prepare($sql);
  $qStmt->bind_param('i', $selected_storybook);
  $qStmt->execute();
  $questions = $qStmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $qStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Questions – ROSE OF SHARON DAY CARE CENTER</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
<div class="main-content">
  <?php include 'header.php'; ?>

  <!-- FLASH: render messages just under header -->
  <?php if (function_exists('render_flashes')) { render_flashes(); } ?>

  <div class="content-area">
    <h2 class="mb-4 text-center">📝 QUESTION BANK</h2>

    <!-- FILTERS -->
    <form method="GET" class="row g-2 mb-4 align-items-center">
      <div class="col-md-4">
        <select name="storybook_id" class="form-select" onchange="this.form.submit()">
          <option value="">📚 Filter by Storybook</option>
          <?php foreach ($allBooks as $book): ?>
            <option value="<?= (int)$book['id'] ?>" <?= ($selected_storybook === (int)$book['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($book['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <!-- NEW: search storybooks by title (replaces question/keyword search) -->
        <input type="text" name="book_search" value="<?= htmlspecialchars($bookSearch) ?>" class="form-control" placeholder="🔍 Search storybooks by title">
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">🔍 Filter</button>
      </div>
      <div class="col-md-1">
        <a href="questions.php" class="btn btn-outline-secondary w-100">🔄 Reset</a>
      </div>
    </form>

    <!-- STORYBOOK LIST -->
    <div class="card mb-4">
      <div class="card-header bg-dark text-white">Storybooks</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-hover mb-0">
            <thead class="table-light"><tr><th>Storybook Title</th></tr></thead>
            <tbody>
              <?php foreach ($storybooks as $book): ?>
                <tr>
                  <td>
                    <a href="questions.php?storybook_id=<?= (int)$book['id'] ?>&book_search=<?= urlencode($bookSearch) ?>" class="btn btn-link text-decoration-none">
                      📘 <?= htmlspecialchars($book['title']) ?>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($storybooks)): ?>
                <tr><td class="text-center text-muted">No active storybooks<?= $bookSearch ? ' matching your search.' : '.' ?></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PAGINATION -->
    <nav>
      <ul class="pagination justify-content-center">
        <?php
          // Preserve selected storybook and book_search across pages
          $baseQS = 'storybook_id=' . urlencode($selected_storybook) . '&book_search=' . urlencode($bookSearch);
        ?>
        <?php if ($page > 1): ?>
          <li class="page-item">
            <a class="page-link" href="?<?= $baseQS ?>&page=<?= $page - 1 ?>">Previous</a>
          </li>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
            <a class="page-link" href="?<?= $baseQS ?>&page=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <li class="page-item">
            <a class="page-link" href="?<?= $baseQS ?>&page=<?= $page + 1 ?>">Next</a>
          </li>
        <?php endif; ?>
      </ul>
    </nav>

    <?php if ($selected_storybook): ?>
      <hr>
      <h4 class="mb-3">
        Questions for:
        <span class="text-primary">
          <?php foreach ($allBooks as $b) { if ((int)$b['id'] === $selected_storybook) { echo htmlspecialchars($b['title']); break; } } ?>
        </span>
      </h4>

      <!-- ADD QUESTION -->
      <div class="card mb-4">
        <div class="card-header bg-primary text-white">Add New Question</div>
        <div class="card-body">
          <form action="questions_add_save.php" method="POST" class="row g-3">
            <input type="hidden" name="storybook_id" value="<?= (int)$selected_storybook ?>">
            <div class="col-md-4">
              <label class="form-label">Question</label>
              <input type="text" name="question_text" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Correct Keywords</label>
              <input type="text" name="correct_keywords" class="form-control" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Language</label>
              <select name="language" class="form-select" required>
                <option value="en">English</option>
                <option value="fil">Tagalog</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Set</label>
              <select name="question_set" class="form-select" required>
                <option value="assessment">Assessment</option>
                <option value="recap">Recap</option>
              </select>
            </div>
            <div class="col-md-12 d-flex justify-content-end">
              <button type="submit" class="btn btn-success">➕ Add Question</button>
            </div>
          </form>
        </div>
      </div>

      <!-- QUESTIONS TABLE -->
      <div class="card">
        <div class="card-header bg-secondary text-white">Existing Questions</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
              <thead class="table-dark">
                <tr>
                  <th>Question</th>
                  <th>Keywords</th>
                  <th>Language</th>
                  <th>Set</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($questions)): ?>
                  <tr><td colspan="5" class="text-center text-muted">No questions available.</td></tr>
                <?php else: ?>
                  <?php foreach ($questions as $q): ?>
                    <tr>
                      <td><?= htmlspecialchars($q['question_text']) ?></td>
                      <td><?= htmlspecialchars($q['correct_keywords']) ?></td>
                      <td><?= ucfirst(htmlspecialchars($q['language'])) ?></td>
                      <td><?= ucfirst(htmlspecialchars($q['question_set'])) ?></td>
                      <td>
                        <a href="questions_edit.php?id=<?= (int)$q['id'] ?>" class="btn btn-sm btn-warning">✏️ Edit</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
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
