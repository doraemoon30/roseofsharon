<?php 
require 'db.php';
require 'auth_check.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$session_id      = $_GET['session_id'] ?? $_POST['session_id'] ?? null;

// ✅ Automatically detect type from notes column
$type = 'assessment'; // default
if ($session_id) {
    $typeStmt = $conn->prepare("SELECT notes FROM sessions WHERE id = ?");
    $typeStmt->bind_param("i", $session_id);
    $typeStmt->execute();
    $typeResult = $typeStmt->get_result();
    if ($row = $typeResult->fetch_assoc()) {
        $type = strtolower(trim($row['notes'])) === 'recap' ? 'recap' : 'assessment';
    }
    $typeStmt->close();
}

$current_q_index = $_POST['current_q_index'] ?? 0;
$retry           = isset($_POST['retry']);
$recognized      = '';
$score           = null;
$total_score     = $_POST['total_score'] ?? 0;
$correct_count   = $_POST['correct_count'] ?? 0;

$isAssessed = false;
if ($session_id && $type === 'assessment') { 
    $check = $conn->prepare("SELECT assessed FROM sessions WHERE id = ?");
    $check->bind_param("i", $session_id);
    $check->execute();
    $checkResult = $check->get_result();
    if ($checkRow = $checkResult->fetch_assoc()) {
        if ($checkRow['assessed'] == 1) {
            $isAssessed = true;
        }
    }
}

if ($isAssessed) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>Assessment – ROSE OF SHARON</title>
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    </head>
    <body class="bg-warning-subtle text-center p-5">
      <h3>⚠️ Session Already Assessed</h3>
      <p>This session has already been assessed. You cannot take the assessment again.</p>
      <a href="dashboard.php" class="btn btn-outline-primary mt-3">← Back to Dashboard</a>
    </body>
    </html>';
    exit;
}

$questions = [];
if ($session_id) {
    $qstmt = $conn->prepare("
        SELECT q.*, s.title AS story_title
        FROM questions q
        JOIN storybooks s ON q.storybook_id = s.id
        JOIN sessions sess ON sess.storybook_id = s.id
        WHERE sess.id = ? AND q.question_set = ?
        ORDER BY q.id ASC
        LIMIT 3
    ");
    $qstmt->bind_param("is", $session_id, $type); // 'assessment' or 'recap'
    $qstmt->execute();
    $questions = $qstmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

if (
    $session_id &&
    isset($_POST['record']) &&
    isset($_POST['recognized']) &&
    isset($questions[$current_q_index])
) {
    $recognized = preprocessSpeech(trim($_POST['recognized']));
    $isCorrect = isGroupAnswerCorrect($recognized, $questions[$current_q_index]['correct_keywords']);
    $score = $isCorrect ? 1 : 0;
    $total_score += $score;
    if ($isCorrect) $correct_count++;

    // ✅ Save only if type is assessment
    if ($type === 'assessment') { 
        $stmt = $conn->prepare("
          INSERT INTO assessment_results
            (session_id, question_id, recognized_answer, correct_keywords, match_score)
          VALUES (?, ?, ?, ?, ?)
        ");
        $qid = $questions[$current_q_index]['id'];
        $stmt->bind_param(
          "isssi",
          $session_id,
          $qid,
          $recognized,
          $questions[$current_q_index]['correct_keywords'],
          $score
        );
        $stmt->execute();
    }

    // ✅ Always sync feedback for both assessment and recap
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

    $current_q_index++;

    if ($current_q_index >= count($questions) && $type === 'assessment') { 
        // ✅ Mark session as assessed and update usage
        $conn->query("UPDATE sessions SET assessed = 1 WHERE id = $session_id");

        $conn->query("
            UPDATE storybooks 
            SET used_count = used_count + 1 
            WHERE id = (SELECT storybook_id FROM sessions WHERE id = $session_id)
        ");
    }
}

// ---------------------
// Helper Functions
// ---------------------

function isGroupAnswerCorrect($recognized, $expected) {
    $fillers = ['the', 'a', 'an', 'is', 'are', 'was', 'were'];
    $recognized = strtolower(preg_replace("/[^\w\s]/", "", $recognized));
    $expected   = strtolower(preg_replace("/[^\w\s,]/", "", $expected));
    foreach ($fillers as $filler) {
        $recognized = preg_replace("/\\b$filler\\b/", "", $recognized);
        $expected   = preg_replace("/\\b$filler\\b/", "", $expected);
    }
    $recognizedWords = array_filter(explode(' ', $recognized));
    $expectedKeywords = array_map('trim', explode(',', $expected));
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
                list($wrong, $correct) = explode('=', $line, 2);
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
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.9.6/lottie.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css">
  <style>
    body { background: #f5f5f5; font-family: 'Segoe UI', sans-serif; }
    .container { padding: 40px; }
    .card { border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 30px; }
    .shake { animation: shake 1.5s; } /* ⬅ longer */
    @keyframes shake {
      0%,100%{transform:translate(1px,-2px) rotate(-1deg);}
      10%,90%{transform:translate(-1px,-2px) rotate(1deg);}
      20%,80%{transform:translate(-3px,0) rotate(0);}
      30%,70%{transform:translate(3px,2px) rotate(0);}
      40%,60%{transform:translate(1px,-1px) rotate(1deg);}
      50%{transform:translate(-1px,2px) rotate(-1deg);}
    }
    /* Wrong answer visual overlay */
    .wrong-overlay {
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      background: rgba(255,0,0,0.2);
      z-index: 9999;
      animation: fadeOut 2s forwards;
    }
    .wrong-overlay img {
      width: 150px;
      margin-top: 20px;
    }
    .wrong-cross {
      font-size: 8rem; /* bigger ❌ */
      color: red;
    }
    @keyframes fadeOut {
      0% {opacity: 1;}
      80% {opacity: 1;}
      100% {opacity: 0; visibility: hidden;}
    }
  </style>
</head>
<body>
<div class="container">
  <h3 class="mb-4 text-center">🎤 Comprehension <?= ucfirst($type) ?></h3>

  <?php if ($current_q_index < count($questions)): ?>
    <?php $q = $questions[$current_q_index]; ?>
    <div class="card p-4" id="feedbackBox">
      <h5>Question <?= $current_q_index + 1 ?> of <?= count($questions) ?>:</h5>
      <p id="question-text" style="font-size: 3rem; font-weight: bold;"><?= htmlspecialchars($q['question_text']) ?></p>
      <button class="btn btn-outline-dark mb-3" onclick="toggleSpeech()">🔊 Read Aloud</button>

      <button type="button" id="startRecordBtn" class="btn btn-success">🎤 Capture (10s)</button>
      <div class="progress mt-2" id="recordProgressBar" style="display:none;height:20px;">
        <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressInnerBar">0%</div>
      </div>

      <form id="recordForm" method="POST" class="mt-3">
        <input type="hidden" name="session_id"    value="<?= $session_id ?>">
        <input type="hidden" name="current_q_index" value="<?= $current_q_index ?>">
        <input type="hidden" name="record"          value="1">
        <input type="hidden" name="recognized"      id="recognizedInput">
        <input type="hidden" name="total_score"     value="<?= $total_score ?>">
        <input type="hidden" name="correct_count"   value="<?= $correct_count ?>">
        <button type="submit" id="submitVoice" style="display:none;"></button>
      </form>
    </div>
  <?php else: ?>
    <div class="card p-4 text-center">
      <h4>🎉 <?= ucfirst($type) ?> Complete!</h4>
      <?php if ($type === 'assessment'): ?>
        <p>You answered <strong><?= $correct_count ?></strong> of <strong><?= count($questions) ?></strong> correctly.</p>
        <p>Your total score: <strong><?= $correct_count ?></strong> / <?= count($questions) ?></p>
      <?php else: ?>
        <p>Recap session has ended.</p>
      <?php endif; ?>
      <a href="dashboard.php" class="btn btn-primary mt-3">← Back to Dashboard</a>
    </div>
  <?php endif; ?>
</div>

<!-- AUDIO & TTS -->
<audio id="correctSound" src="audio/correct.mp3" preload="auto"></audio>
<audio id="wrongSound"   src="audio/wrong.mp3"   preload="auto"></audio>

<script>
let availableVoices = [];
function loadVoices(callback) {
  availableVoices = speechSynthesis.getVoices();
  if (availableVoices.length) {
    callback();
  } else {
    speechSynthesis.onvoiceschanged = () => {
      availableVoices = speechSynthesis.getVoices();
      callback();
    };
  }
}
</script>

<?php if ($score !== null): ?>
<script>
window.addEventListener('DOMContentLoaded', () => {
  const box = document.getElementById('feedbackBox');
  const speak = (text) => {
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.rate = 0.85;
    utterance.pitch = 1.2;
    loadVoices(() => {
      const preferredVoice = availableVoices.find(v =>
        v.name.toLowerCase().includes("female") ||
        v.name.toLowerCase().includes("samantha") ||
        v.name.toLowerCase().includes("zira") ||
        v.name.toLowerCase().includes("eva") ||
        (v.name.toLowerCase().includes("google") && v.name.toLowerCase().includes("english"))
      );
      if (preferredVoice) utterance.voice = preferredVoice;
      speechSynthesis.speak(utterance);
    });
  };

  <?php if ($score >= 1): ?>
    document.getElementById('correctSound').play().catch(() => {});
    confetti({ particleCount: 150, spread: 80, origin: { y: 0.6 } });
    speak("Correct! Great job! hoping you'll get the next question!");
  <?php else: ?>
    document.getElementById('wrongSound').play().catch(() => {});
    box.classList.add('shake');
    // Show overlay with ❌ and crying smiley
    const overlay = document.createElement('div');
    overlay.className = 'wrong-overlay';
    overlay.innerHTML = '<div class="wrong-cross">❌</div><img src="images/crying_smiley.gif" alt="Crying">';
    document.body.appendChild(overlay);
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
    utterance.rate = 0.85;
    utterance.pitch = 1.2;
    loadVoices(() => {
      const preferredVoice = availableVoices.find(v =>
        v.name.toLowerCase().includes("female") ||
        v.name.toLowerCase().includes("samantha") ||
        v.name.toLowerCase().includes("zira") ||
        v.name.toLowerCase().includes("eva") ||
        (v.name.toLowerCase().includes("google") && v.name.toLowerCase().includes("english"))
      );
      if (preferredVoice) utterance.voice = preferredVoice;
      synth.speak(utterance);
      isSpeaking = true;
      utterance.onend = () => isSpeaking = false;
    });
  } else {
    synth.cancel();
    isSpeaking = false;
  }
}
</script>

<script>
const startBtn = document.getElementById('startRecordBtn'),
      bar = document.getElementById('recordProgressBar'),
      inner = document.getElementById('progressInnerBar'),
      formBtn = document.getElementById('submitVoice'),
      recogInput = document.getElementById('recognizedInput');

startBtn?.addEventListener('click', () => {
  if (!('webkitSpeechRecognition' in window)) {
    return alert('Speech Recognition not supported.');
  }
  const recog = new webkitSpeechRecognition(),
        langHints = ['ang','siya','ng','ko','ikaw','niya','kami','kayo','nila','kanila'],
        qtxt = document.getElementById('question-text').textContent.toLowerCase();
  recog.lang = langHints.some(w => qtxt.includes(w)) ? 'fil-PH' : 'en-US';
  recog.continuous = false;
  recog.interimResults = false;
  let sec = 0;
  bar.style.display = 'block';
  inner.style.width = '0%';
  inner.textContent = 'Recording...';
  const iv = setInterval(() => {
    sec++;
    inner.style.width = (sec * 10) + '%';
    inner.textContent = `Recording... ${10 - sec}s`;
    if (sec >= 10) {
      clearInterval(iv);
      recog.stop();
    }
  }, 1000);
  recog.onresult = e => recogInput.value = e.results[0][0].transcript.trim();
  recog.onend = () => formBtn.click();
  recog.start();
});
</script>
</body>
</html>
