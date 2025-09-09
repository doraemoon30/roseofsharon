<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';
// (No need to call helpers.php here; we read AY from the session itself)

ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : (int)($_POST['session_id'] ?? 0);
if ($session_id <= 0) { flash('warning','Missing session.'); header('Location: sessions.php'); exit(); }

// Detect type + academic_year from session
$type = 'assessment';
$AY_session = null;
$typeStmt = $conn->prepare("SELECT notes, academic_year FROM sessions WHERE id = ?");
$typeStmt->bind_param("i", $session_id);
$typeStmt->execute();
$typeRow = $typeStmt->get_result()->fetch_assoc();
$typeStmt->close();
if ($typeRow) {
  $type = (strtolower(trim($typeRow['notes'])) === 'recap') ? 'recap' : 'assessment';
  $AY_session = (string)($typeRow['academic_year'] ?? '');
}

// Prevent re-assessment (for assessment type only)
$isAssessed = false;
if ($type === 'assessment') {
  $check = $conn->prepare("SELECT assessed FROM sessions WHERE id = ?");
  $check->bind_param("i", $session_id);
  $check->execute();
  $row = $check->get_result()->fetch_assoc();
  $check->close();
  $isAssessed = (int)($row['assessed'] ?? 0) === 1;
  if ($isAssessed) { flash('warning','⚠️ This session has already been assessed.'); header('Location: sessions.php'); exit(); }
}

// State from POST
$current_q_index = isset($_POST['current_q_index']) ? (int)$_POST['current_q_index'] : 0;
$recognized      = '';
$score           = null;
$total_score     = isset($_POST['total_score']) ? (int)$_POST['total_score'] : 0;
$correct_count   = isset($_POST['correct_count']) ? (int)$_POST['correct_count'] : 0;

// Fetch up to 3 questions tied to this session's storybook + question_set
$qstmt = $conn->prepare("
  SELECT q.id, q.storybook_id, q.question_text, q.correct_keywords, q.language, q.question_set,
         s.title AS story_title
  FROM questions q
  JOIN storybooks s  ON q.storybook_id = s.id
  JOIN sessions sess ON sess.storybook_id = s.id
  WHERE sess.id = ? AND q.question_set = ?
  ORDER BY q.id ASC
  LIMIT 3
");
$qstmt->bind_param("is", $session_id, $type);
$qstmt->execute();
$questions = $qstmt->get_result()->fetch_all(MYSQLI_ASSOC);
$qstmt->close();

if (empty($questions)) {
  flash('danger','No questions found for this session and set.');
  header('Location: sessions.php'); exit();
}

/**
 * NEW FLOW:
 * 1) Capture (JS) -> show transcript (live/final) on screen.
 * 2) Teacher clicks "Evaluate" -> POST with 'evaluate' and 'recognized' (final transcript).
 */

// Handle an evaluation submit (AFTER transcript is shown)
if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  isset($_POST['evaluate']) &&
  isset($_POST['recognized']) &&
  isset($questions[$current_q_index])
) {
  $recognized = preprocessSpeech(trim((string)$_POST['recognized']));
  $isCorrect  = isGroupAnswerCorrect($recognized, $questions[$current_q_index]['correct_keywords']);
  $score      = $isCorrect ? 1 : 0;
  $total_score += $score;
  if ($isCorrect) $correct_count++;

  // Save result only for assessment (WITH academic_year)
  if ($type === 'assessment') {
    $qid  = (int)$questions[$current_q_index]['id'];
    $stmt = $conn->prepare("
      INSERT INTO assessment_results
        (session_id, question_id, recognized_answer, correct_keywords, match_score, academic_year)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    // types: i (session_id), i (question_id), s (recognized), s (correct_keywords), i (match_score), s (academic_year)
    $types = "iissis";
    $stmt->bind_param($types, $session_id, $qid, $recognized, $questions[$current_q_index]['correct_keywords'], $score, $AY_session);
    $stmt->execute();
    $stmt->close();
  }

  // Sync feedback (both modes) + store last transcript for visibility/debug
  $feedbackValue = $score >= 1 ? 'correct' : 'wrong';
  $stateStmt = $conn->prepare("
  INSERT INTO student_view_state (session_id, current_q_index, feedback)
  VALUES (?, ?, ?)
  ON DUPLICATE KEY UPDATE
    current_q_index = VALUES(current_q_index),
    feedback = VALUES(feedback)
");
$stateStmt->bind_param("iis", $session_id, $current_q_index, $feedbackValue);

  $stateStmt->execute();
  $stateStmt->close();

  $current_q_index++;

  // If finished (assessment): mark assessed + increment storybook usage
  if ($current_q_index >= count($questions) && $type === 'assessment') {
    $conn->query("UPDATE sessions SET assessed = 1 WHERE id = {$session_id}");
    $conn->query("
      UPDATE storybooks 
      SET used_count = used_count + 1 
      WHERE id = (SELECT storybook_id FROM sessions WHERE id = {$session_id})
    ");
  }
}

// ---------------------
// Helpers
// ---------------------
function isGroupAnswerCorrect($recognized, $expected) {
  $fillers = ['the','a','an','is','are','was','were'];
  $recognized = strtolower(preg_replace("/[^\w\s]/", "", $recognized));
  $expected   = strtolower(preg_replace("/[^\w\s,]/", "", $expected));
  foreach ($fillers as $filler) {
    $recognized = preg_replace("/\\b$filler\\b/", "", $recognized);
    $expected   = preg_replace("/\\b$filler\\b/", "", $expected);
  }
  $recognizedWords   = array_filter(explode(' ', $recognized));
  $expectedKeywords  = array_map('trim', explode(',', $expected));
  foreach ($expectedKeywords as $keyword) {
    foreach ($recognizedWords as $word) {
      similar_text($word, $keyword, $percent);
      if ($percent >= 60) return true;
    }
  }
  return false;
}

function preprocessSpeech($text) {
  $mapFile = __DIR__ . '/speech_map.txt';
  if (file_exists($mapFile)) {
    $lines = file($mapFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      if (strpos($line, '=') !== false) {
        [$wrong, $correct] = explode('=', $line, 2);
        $text = str_ireplace(trim($wrong), trim($correct), $text);
      }
    }
  }
  return $text;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Assessment – ROSE OF SHARON DAY CARE CENTER</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 (align with the rest of the app) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    body { background: #f5f5f5; font-family: 'Segoe UI', sans-serif; }
    .container { padding: 40px; }
    .card { border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
    .shake { animation: shake 0.6s; }
    @keyframes shake {
      0%,100%{transform:translate(1px,-2px) rotate(-1deg);}
      10%,90%{transform:translate(-1px,-2px) rotate(1deg);}
      20%,80%{transform:translate(-3px,0) rotate(0);}
      30%,70%{transform:translate(3px,2px) rotate(0);}
      40%,60%{transform:translate(1px,-1px) rotate(1deg);}
      50%{transform:translate(-1px,2px) rotate(-1deg);}
    }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }
  </style>
</head>
<body>

<div class="main-content">
  <!-- FLASH: unified placement -->
  <?php if (function_exists('render_flashes')) { render_flashes(); } ?>

  <div class="container">
    <h3 class="mb-4 text-center">🎤 Comprehension <?= htmlspecialchars(ucfirst($type)) ?></h3>

    <?php if ($current_q_index < count($questions)): ?>
      <?php $q = $questions[$current_q_index]; ?>
      <div class="card p-4" id="feedbackBox">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0">Question <?= $current_q_index + 1 ?> of <?= count($questions) ?>:</h5>
          <div class="text-muted small"><?= htmlspecialchars($q['story_title']) ?> · Set: <?= htmlspecialchars($q['question_set']) ?></div>
        </div>

        <p id="question-text" style="font-size: 2.25rem; font-weight: 700;"><?= htmlspecialchars($q['question_text']) ?></p>

        <!-- Controls -->
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-outline-dark" onclick="toggleSpeech()">🔊 Read Aloud</button>
          <button type="button" id="startRecordBtn" class="btn btn-success">🎤 Capture (10s)</button>
          <button type="button" id="btnEvaluate" class="btn btn-warning" disabled>✅ Evaluate</button>
        </div>

        <!-- Progress -->
        <div class="progress mt-3" id="recordProgressBar" style="display:none; height:20px;">
          <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressInnerBar" style="width:0%">0%</div>
        </div>

        <!-- Transcript panel (NEW) -->
        <div class="row mt-3 g-3">
          <div class="col-12 col-md-6">
            <div class="card">
              <div class="card-header fw-bold">Student Answer (Live)</div>
              <div class="card-body">
                <div id="liveTranscript" class="mono p-2 bg-light border rounded" style="min-height:56px"></div>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="card">
              <div class="card-header fw-bold">Student Answer (Final)</div>
              <div class="card-body">
                <div id="finalTranscript" class="mono p-2 bg-light border rounded" style="min-height:56px"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Hidden form posted only on Evaluate -->
        <form id="evaluateForm" method="POST" class="mt-3">
          <input type="hidden" name="session_id"        value="<?= (int)$session_id ?>">
          <input type="hidden" name="current_q_index"   value="<?= (int)$current_q_index ?>">
          <input type="hidden" name="evaluate"          value="1">
          <input type="hidden" name="recognized"        id="recognizedInput">
          <input type="hidden" name="total_score"       value="<?= (int)$total_score ?>">
          <input type="hidden" name="correct_count"     value="<?= (int)$correct_count ?>">
        </form>
      </div>
    <?php else: ?>
      <div class="card p-4 text-center">
        <h4>🎉 <?= htmlspecialchars(ucfirst($type)) ?> Complete!</h4>
        <?php if ($type === 'assessment'): ?>
          <p>You answered <strong><?= (int)$correct_count ?></strong> of <strong><?= (int)count($questions) ?></strong> correctly.</p>
          <p>Your total score: <strong><?= (int)$correct_count ?></strong> / <?= (int)count($questions) ?></p>
        <?php else: ?>
          <p>Recap session has ended.</p>
        <?php endif; ?>
        <a href="dashboard.php" class="btn btn-primary mt-3">← Back to Dashboard</a>
      </div>
    <?php endif; ?>
  </div>

  <?php include 'footer.php'; ?>
</div>

<!-- Audio cues -->
<audio id="correctSound" src="audio/correct.mp3" preload="auto"></audio>
<audio id="wrongSound"   src="audio/wrong.mp3"   preload="auto"></audio>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.9.6/lottie.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<script>
let availableVoices = [];
function loadVoices(callback) {
  availableVoices = speechSynthesis.getVoices();
  if (availableVoices.length) {
    callback();
  } else {
    speechSynthesis.onvoiceschanged = () => { availableVoices = speechSynthesis.getVoices(); callback(); };
  }
}
</script>

<?php if ($score !== null): ?>
<script>
window.addEventListener('DOMContentLoaded', () => {
  const box = document.getElementById('feedbackBox');
  const speak = (text) => {
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.rate = 0.85; utterance.pitch = 1.2;
    loadVoices(() => {
      const preferred = availableVoices.find(v =>
        v.name.toLowerCase().includes("female") ||
        v.name.toLowerCase().includes("samantha") ||
        v.name.toLowerCase().includes("zira") ||
        v.name.toLowerCase().includes("eva") ||
        (v.name.toLowerCase().includes("google") && v.name.toLowerCase().includes("english"))
      );
      if (preferred) utterance.voice = preferred;
      speechSynthesis.speak(utterance);
    });
  };

  <?php if ($score >= 1): ?>
    document.getElementById('correctSound').play().catch(()=>{});
    confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 } });
    speak("Correct! Great job! hoping you'll get the next question!");
  <?php else: ?>
    document.getElementById('wrongSound').play().catch(()=>{});
    box.classList.add('shake');
    speak("Oops! Better listen carefully next time.");
  <?php endif; ?>
});
</script>
<?php endif; ?>

<script>
let synth = window.speechSynthesis, utterance, isSpeaking = false;
function toggleSpeech() {
  const text = document.getElementById("question-text").textContent;
  if (!isSpeaking) {
    utterance = new SpeechSynthesisUtterance(text);
    utterance.rate = 0.85; utterance.pitch = 1.2;
    loadVoices(() => {
      const preferred = availableVoices.find(v =>
        v.name.toLowerCase().includes("female") ||
        v.name.toLowerCase().includes("samantha") ||
        v.name.toLowerCase().includes("zira") ||
        v.name.toLowerCase().includes("eva") ||
        (v.name.toLowerCase().includes("google") && v.name.toLowerCase().includes("english"))
      );
      if (preferred) utterance.voice = preferred;
      synth.speak(utterance);
      isSpeaking = true;
      utterance.onend = () => isSpeaking = false;
    });
  } else {
    synth.cancel(); isSpeaking = false;
  }
}
</script>

<script>
// ===== NEW capture -> show transcript -> Evaluate =====
const startBtn = document.getElementById('startRecordBtn'),
      btnEval = document.getElementById('btnEvaluate'),
      bar = document.getElementById('recordProgressBar'),
      inner = document.getElementById('progressInnerBar'),
      recogInput = document.getElementById('recognizedInput'),
      liveEl = document.getElementById('liveTranscript'),
      finalEl = document.getElementById('finalTranscript'),
      formEval = document.getElementById('evaluateForm');

let recognition = null, capturing = false, interimText = '', finalText = '';

function initRecog(){
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) { alert('Speech Recognition not supported in this browser.'); return null; }
  const r = new SR();
  // language auto-hint from question
  const qtxt = (document.getElementById('question-text')?.textContent || '').toLowerCase();
  const langHints = ['ang','siya','ng','ko','ikaw','niya','kami','kayo','nila','kanila'];
  r.lang = langHints.some(w => qtxt.includes(w)) ? 'fil-PH' : 'en-US';

  r.interimResults = true;
  r.continuous = true;

  r.onresult = (e) => {
    interimText = '';
    for (let i = e.resultIndex; i < e.results.length; i++) {
      const t = e.results[i][0].transcript;
      if (e.results[i].isFinal) {
        finalText += (finalText ? ' ' : '') + t.trim();
      } else {
        interimText += t;
      }
    }
    liveEl.textContent = interimText.trim();
    finalEl.textContent = finalText.trim();
  };

  r.onend = () => {
    capturing = false;
    inner.textContent = 'Recording finished';
    // enable Evaluate if we have a final transcript
    btnEval.disabled = (finalText.trim() === '');
  };

  return r;
}

startBtn?.addEventListener('click', () => {
  if (capturing) return;
  // reset views
  interimText = ''; finalText = '';
  liveEl.textContent = '';
  finalEl.textContent = '';
  recogInput.value = '';
  btnEval.disabled = true;

  recognition = initRecog();
  if (!recognition) return;

  capturing = true;
  recognition.start();

  // visual progress for 10s
  let sec = 0;
  bar.style.display = 'block';
  inner.style.width = '0%';
  inner.textContent = 'Recording...';
  const iv = setInterval(() => {
    sec++;
    inner.style.width = (sec * 10) + '%';
    inner.textContent = `Recording... ${10 - sec}s`;
    if (sec >= 10) { clearInterval(iv); try { recognition.stop(); } catch(e) {} }
  }, 1000);
});

// On Evaluate -> submit final transcript to PHP
btnEval?.addEventListener('click', () => {
  const t = (finalEl.textContent || '').trim();
  if (!t) return;
  recogInput.value = t;
  formEval.submit();
});
</script>
</body>
</html>
