<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';

// 1) Sanitize input (base name only, no extension)
$fileBase = $_GET['file'] ?? '';
$fileBase = preg_replace('/[^A-Za-z0-9_-]/', '', $fileBase);

if ($fileBase === '') {
  flash('warning', 'Missing or invalid video reference.');
  header('Location: storybooks.php'); exit();
}

// 2) Look up storybook by exact filename and ensure it's active
$filename = $fileBase . '.mp4';
$stmt = $conn->prepare("SELECT id, title, filename, description FROM storybooks WHERE filename = ? AND deleted_at IS NULL LIMIT 1");
$stmt->bind_param('s', $filename);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$book) {
  flash('danger', 'Storybook not found or archived.');
  header('Location: storybooks.php'); exit();
}

// 3) Resolve and validate the actual file path
$baseDir = realpath(__DIR__ . '/assets/storybooks');
$videoAbs = realpath(__DIR__ . '/assets/storybooks/' . $book['filename']);
$videoRel = 'assets/storybooks/' . $book['filename'];
if (!$videoAbs || !$baseDir || strpos($videoAbs, $baseDir) !== 0 || !is_file($videoAbs)) {
  flash('danger', 'Video file is missing on the server.');
  header('Location: storybooks.php'); exit();
}

// 4) Get the latest session for this storybook
$session = null;
$ss = $conn->prepare("SELECT id, notes, assessed, session_datetime FROM sessions WHERE storybook_id = ? ORDER BY session_datetime DESC, id DESC LIMIT 1");
$ss->bind_param('i', $book['id']);
$ss->execute();
$session = $ss->get_result()->fetch_assoc();
$ss->close();

$isRecap   = $session ? (strtolower(trim((string)$session['notes'])) === 'recap') : false;
$isLocked  = $session ? (!$isRecap && (int)$session['assessed'] === 1) : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($book['title']) ?> – Preview</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-family:'Segoe UI',sans-serif; background:#0b132b; color:#fff; }
    .wrap { max-width: 1100px; margin: 24px auto; padding: 16px; }
    .card { background:#1c2541; border:none; }
    .card .card-header { background:#3a506b; color:#fff; }
    video { width:100%; border-radius:12px; background:#000; }
    .small-note { opacity:.85; font-size:.9rem; }
  </style>
</head>
<body>

<div class="main-content">
  <?php include 'header.php'; ?>
  <?php if (function_exists('render_flashes')) render_flashes(); ?>

  <div class="wrap">
    <div class="card shadow">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>🎬 Preview: <?= htmlspecialchars($book['title']) ?></strong>
        <div class="d-flex gap-2">
          <a href="storybooks.php" class="btn btn-sm btn-outline-light">← Story Bank</a>
          <a href="sessions.php" class="btn btn-sm btn-outline-light">📋 Sessions</a>
        </div>
      </div>
      <div class="card-body">
        <video id="player" src="<?= htmlspecialchars($videoRel) ?>" controls playsinline autoplay></video>

        <div class="mt-3 d-flex gap-2 flex-wrap">
          <button class="btn btn-primary btn-sm" onclick="toggleFullscreen()">⛶ Fullscreen</button>
          <button class="btn btn-secondary btn-sm" onclick="document.getElementById('player').currentTime=0">↺ Restart</button>

          <?php if ($session): ?>
            <?php if ($isLocked): ?>
              <span class="badge bg-secondary align-self-center">🔒 Latest assessment completed</span>
            <?php else: ?>
              <a
                href="session_video_start.php?session_id=<?= (int)$session['id'] ?>"
                class="btn <?= $isRecap ? 'btn-info' : 'btn-success' ?> btn-sm"
              >
                <?= $isRecap ? '🔄 Launch Recap' : '🚀 Launch Session' ?>
              </a>
            <?php endif; ?>
            <span class="small-note ms-2">
              Latest session: <?= date("F j, Y g:i A", strtotime($session['session_datetime'])) ?>
              (<?= $isRecap ? 'recap' : 'assessment' ?>)
            </span>
          <?php else: ?>
            <span class="small-note">No session exists yet for this storybook.</span>
          <?php endif; ?>
        </div>

        <?php if (!empty($book['description'])): ?>
          <p class="mt-3 text-light small mb-0"><?= nl2br(htmlspecialchars($book['description'])) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>
</div>

<script>
function toggleFullscreen(){
  const v = document.getElementById('player');
  if (!document.fullscreenElement) {
    (v.requestFullscreen || v.webkitRequestFullscreen || v.msRequestFullscreen)?.call(v);
  } else {
    (document.exitFullscreen || document.webkitExitFullscreen || document.msExitFullscreen)?.call(document);
  }
}
</script>
</body>
</html>
