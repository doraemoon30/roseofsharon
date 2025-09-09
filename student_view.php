<?php
require 'db.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Anti-cache for kiosk screens
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
if ($session_id <= 0) { http_response_code(400); echo "Missing session_id."; exit; }

// Pull video filename, session type (notes), storybook_id, datetime, and academic_year
$video_filename = '';
$notes = '';
$storybook_id = 0;
$session_dt = null;
$ay_session = '';

$stmt = $conn->prepare("
  SELECT sb.filename, s.notes, sb.id AS storybook_id, s.session_datetime, s.academic_year
  FROM sessions s
  JOIN storybooks sb ON s.storybook_id = sb.id
  WHERE s.id = ?
  LIMIT 1
");
$stmt->bind_param("i", $session_id);
$stmt->execute();
$stmt->bind_result($video_filename, $notes, $storybook_id, $session_dt, $ay_session);
$stmt->fetch();
$stmt->close();

if (!$video_filename) { http_response_code(404); echo "Session not found or storybook missing."; exit; }

$type = (strtolower(trim($notes)) === 'recap') ? 'recap' : 'assessment';

// Count questions for progress (based on session type)
$qCount = 0;
$qStmt = $conn->prepare("SELECT COUNT(*) FROM questions WHERE storybook_id = ? AND question_set = ?");
$qStmt->bind_param('is', $storybook_id, $type);
$qStmt->execute();
$qStmt->bind_result($qCount);
$qStmt->fetch();
$qStmt->close();

// Verify video file exists (path safety)
$baseDir  = realpath(__DIR__ . '/assets/storybooks');
$videoAbs = realpath(__DIR__ . '/assets/storybooks/' . $video_filename);
$videoRel = 'assets/storybooks/' . $video_filename;
$videoOK  = ($baseDir && $videoAbs && strpos($videoAbs, $baseDir) === 0 && is_file($videoAbs));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student View – ROSE OF SHARON</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<style>
  html, body { margin:0; padding:0; height:100%; overflow:hidden; font-family:'Segoe UI',sans-serif; background:#000; }
  video { width:100vw; height:100vh; object-fit:cover; }
  #fullscreenBtn { position:fixed; top:10px; right:10px; z-index:999; }

  /* Owl + animations */
  #owl-narrator, #owl-center { position:fixed; z-index:20; display:none; }
  #owl-fly { width:600px; }
  #owl-center { bottom:5%; left:50%; transform:translateX(-50%); }
  @keyframes flyAround {
    0%   { top:10%; left:10%; transform: scale(0.7); }
    20%  { top:25%; left:80%; transform: scale(0.8); }
    40%  { top:70%; left:60%; transform: scale(0.9); }
    60%  { top:30%; left:30%; transform: scale(0.95); }
    80%  { top:20%; left:75%; transform: scale(0.98); }
    100% { top:50%; left:50%; transform:translate(-50%,-50%) scale(1); }
  }
  @keyframes wiggleCenter {
    0%,100% { transform: translateX(-50%) rotate(0) }
    25%     { transform: translateX(-50%) rotate(-5deg) }
    50%     { transform: translateX(-50%) rotate(5deg) }
    75%     { transform: translateX(-50%) rotate(-3deg) }
  }
  @keyframes bob {
    0%,100% { transform: translateX(-50%) translateY(0); }
    50%     { transform: translateX(-50%) translateY(-8px); }
  }
  #owl-center.bobbing { animation: bob 2.8s ease-in-out infinite; }

  .lets-answer {
    position:fixed; top:10%; left:50%; transform:translateX(-50%);
    font-size:3rem; color:yellow; z-index:30; font-weight:bold; display:none;
    text-shadow:0 4px 14px rgba(0,0,0,.6);
  }
  #feedback { position:fixed; top:20%; left:50%; transform:translateX(-50%); font-size:120px; z-index:30; text-shadow:0 6px 22px rgba(0,0,0,.5); }
  #crying-smiley,
  #happy-smiley{
    position: fixed;
    bottom: 2%; right: 2%;
    width: 360px; max-width: 40vw;
    z-index: 21; display: none; pointer-events: none;
  }
  #red-flash { position:fixed; inset:0; background:rgba(255,0,0,.5); z-index:10000; display:none; }
  .fade-screen {
    position:fixed; inset:0; background:#000; z-index:9999; display:flex; justify-content:center; align-items:center;
    font-size:2rem; color:#fff; opacity:0; transition:opacity 2.5s ease;
  }
  .fade-screen.show { opacity:1; }
  .balloon{
    position:fixed; width:80px; height:100px; border-radius:50% 50% 45% 45%;
    opacity:.95; pointer-events:none;
    animation: floatScatter var(--dur) ease-in forwards var(--delay);
  }
  @keyframes floatScatter{
    0%   { transform: translate(0,0) scale(var(--scale,1)); opacity:1; }
    100% { transform: translate(var(--dx,0), var(--dy,-80vh)) scale(calc(var(--scale,1)*1.2)); opacity:0; }
  }
  .topbar { position:fixed; top:8px; left:8px; z-index:900; color:#fff; font-weight:700; }
  .topbar .chip { background:#3ddc84; color:#000; padding:4px 8px; border-radius:999px; font-size:12px; font-weight:700; margin-left:8px; }
  .topbar .note { font-size:12px; opacity:.9; margin-left:8px; background:#222; padding:3px 6px; border-radius:6px; border:1px solid rgba(255,255,255,.2); }
  .topbar .ay { background:#0d6efd; color:#fff; }
  .tear {
    position: fixed; top: -8vh; left: 0; font-size: 28px;
    opacity: 0.9; z-index: 22; pointer-events: none;
    filter: drop-shadow(0 2px 2px rgba(0,0,0,.25));
    animation: tearFall var(--dur, 4s) linear forwards;
  }
  @keyframes tearFall {
    0%   { transform: translate(var(--drift, 0px), -8vh)  scale(var(--scale,1));   opacity: 0.9; }
    85%  { transform: translate(var(--drift, 0px), 88vh)  scale(var(--scale,1.05)); opacity: 0.9; }
    100% { transform: translate(var(--drift, 0px), 108vh) scale(var(--scale,1.08)); opacity: 0;   }
  }
</style>
</head>
<body>

<div class="topbar">
  <span>🎬 Student View</span>
  <span class="chip"><?= $type === 'recap' ? 'RECAP' : 'ASSESSMENT' ?></span>
  <?php if ($session_dt): ?><span class="note"><?= date("M j, Y g:i A", strtotime($session_dt)) ?></span><?php endif; ?>
  <?php if (!empty($ay_session)): ?><span class="chip ay">AY <?= htmlspecialchars($ay_session) ?></span><?php endif; ?>
  <?php if ($qCount > 0): ?><span class="note" id="qProgress">Ready</span><?php endif; ?>
</div>

<button id="fullscreenBtn" onclick="openFullscreen()" class="btn btn-warning btn-lg">🔲 Full Screen</button>

<?php if ($videoOK): ?>
  <video id="storybookVideo" autoplay controls playsinline>
    <source src="<?= htmlspecialchars($videoRel) ?>" type="video/mp4">
  </video>
<?php else: ?>
  <div class="fade-screen show">⚠ Video file missing.</div>
<?php endif; ?>

<div id="owl-narrator"><img id="owl-fly" src="assets/owlbgif.gif" alt=""></div>
<img id="owl-center" src="assets/owlb.gif" alt="Owl">

<div id="letsAnswerText" class="lets-answer" aria-live="polite">Let’s Answer!</div>
<div id="feedback" role="status" aria-live="assertive"></div>
<img id="crying-smiley" src="assets/cry.gif" alt="Sad">
<img id="happy-smiley"  src="assets/happy2.gif" alt="Happy">
<div id="red-flash"></div>

<audio id="correctSound" src="audio/correct.mp3" preload="auto"></audio>
<audio id="wrongSound" src="audio/wrong.mp3" preload="auto"></audio>

<script>
const totalQuestions = <?= (int)$qCount ?>;
const sessionType = <?= json_encode($type) ?>;
const ANIM_DURATION = 10000; // 10s feedback window
const FLY_DURATION  = 6000;  // owl fly time

let phase = 'video', lastIndex = -1, indexAnimated = new Set(), pollTimer = null;

const qProgress   = document.getElementById('qProgress'),
      owlCenter   = document.getElementById('owl-center'),
      letsAnswer  = document.getElementById('letsAnswerText'),
      feedbackEl  = document.getElementById('feedback'),
      crying      = document.getElementById('crying-smiley'),
      happy       = document.getElementById('happy-smiley'),
      redFlash    = document.getElementById('red-flash'),
      fsBtn       = document.getElementById('fullscreenBtn'),
      videoEl     = document.getElementById('storybookVideo');

function openFullscreen(){
  const el=document.documentElement;
  (el.requestFullscreen||el.webkitRequestFullscreen||el.msRequestFullscreen)?.call(el);
  if(fsBtn)fsBtn.style.display='none';
}

function showBalloons(count=80){
  for(let i=0;i<count;i++){
    const b=document.createElement('div');
    b.className='balloon';
    b.style.left=(Math.random()*100)+'vw';
    b.style.top=(Math.random()*100)+'vh';
    b.style.background=`hsl(${Math.random()*360},80%,60%)`;
    b.style.setProperty('--scale',(0.6+Math.random()*0.9).toString());
    b.style.setProperty('--dur',(6+Math.random()*4)+'s');
    b.style.setProperty('--delay',(Math.random()*0.8)+'s');
    const dx=(Math.random()*300-150),dy=-(60+Math.random()*80)+'vh';
    b.style.setProperty('--dx',dx+'px');
    b.style.setProperty('--dy',dy);
    document.body.appendChild(b);
    const ttl=(parseFloat(b.style.getPropertyValue('--dur'))+parseFloat(b.style.getPropertyValue('--delay')))*1000+500;
    setTimeout(()=>b.remove(),ttl);
  }
}

function bigConfetti(duration=ANIM_DURATION){
  const end=Date.now()+duration;
  (function frame(){
    confetti({particleCount:20,spread:90,startVelocity:55,origin:{x:Math.random(),y:Math.random()*0.4}});
    if(Date.now()<end)requestAnimationFrame(frame);
  })();
}

function showCorrect(){
  feedbackEl.textContent='✔️';
  document.getElementById('correctSound').play().catch(()=>{});
  // happy gif on bottom-right
  if (crying) $(crying).stop(true,true).hide();
  if (happy) {
    $(happy).stop(true,true).fadeIn(150);
    if (showCorrect._happyTimer) clearTimeout(showCorrect._happyTimer);
    showCorrect._happyTimer = setTimeout(()=> $(happy).fadeOut(300), ANIM_DURATION);
  }
  showBalloons(100);
  bigConfetti(ANIM_DURATION);
}

function rainTears(durationMs=10000){
  const start=Date.now(),spawnEveryMs=90,maxLifeMs=7000;
  const spawn=()=>{
    if(Date.now()-start>durationMs)return;
    const t=document.createElement('div');
    t.className='tear';
    t.textContent='💧';
    t.style.left=(2+Math.random()*96)+'vw';
    const driftPx=(Math.random()*120-60),scale=0.8+Math.random()*0.8,durSec=3.2+Math.random()*3.0,fontPx=22+Math.random()*26;
    t.style.setProperty('--drift',driftPx+'px');
    t.style.setProperty('--scale',scale.toFixed(2));
    t.style.setProperty('--dur',durSec.toFixed(2)+'s');
    t.style.fontSize=fontPx+'px';
    document.body.appendChild(t);
    setTimeout(()=>t.remove(),Math.min((durSec*1000)+200,maxLifeMs));
    setTimeout(spawn,spawnEveryMs);
  };
  spawn();
}

function showWrong(){
  feedbackEl.textContent='❌';
  feedbackEl.style.fontSize='8rem';
  const audio=document.getElementById('wrongSound');
  audio&&audio.play().catch(()=>{});
  // hide happy if visible
  if (happy) $(happy).stop(true,true).hide();
  $(redFlash).stop(true,true).fadeIn(150).fadeOut(600);
  if(showWrong._cryTimer) clearTimeout(showWrong._cryTimer);
  $(crying).stop(true,true).fadeIn(150);
  showWrong._cryTimer=setTimeout(()=>$(crying).fadeOut(300),10000);
  rainTears(10000);
}

function clearFeedback(){
  feedbackEl.textContent='';
  feedbackEl.style.fontSize='';
  if (happy)  $(happy).stop(true,true).hide();
  if (crying) $(crying).stop(true,true).hide();
}

function startFlySequence(){
  phase='fly'; openFullscreen();
  if(videoEl){
    videoEl.style.transition='opacity 1.2s ease';
    videoEl.style.opacity='0';
    setTimeout(()=>{
      const fadeBg=document.createElement('div');
      fadeBg.style.position='fixed';fadeBg.style.inset='0';
      fadeBg.style.backgroundImage="url('assets/images/storytell.png')";
      fadeBg.style.backgroundRepeat="no-repeat";
      fadeBg.style.backgroundSize="cover";
      fadeBg.style.backgroundPosition="center";
      fadeBg.style.opacity='0';fadeBg.style.transition='opacity 1.5s ease';fadeBg.style.zIndex='0';
      document.body.appendChild(fadeBg); videoEl.remove();
      setTimeout(()=>{
        fadeBg.style.opacity='1';
        setTimeout(()=>{
          const owlFlyWrap=document.getElementById('owl-narrator');
          owlFlyWrap.style.display='block';
          owlFlyWrap.style.animation=`flyAround ${FLY_DURATION/1000}s ease-in-out forwards`;
          setTimeout(()=>{
            owlFlyWrap.style.display='none'; owlFlyWrap.style.animation='';
            owlCenter.style.display='block'; owlCenter.classList.add('bobbing');
            owlCenter.style.animation='wiggleCenter .8s ease';
            setTimeout(()=>{owlCenter.style.animation='';},900);
            startCue();
          },FLY_DURATION);
        },1500);
      },50);
    },1200);
  }
}

function startCue(){
  phase='cue'; $(letsAnswer).fadeIn(600);
  if(qProgress&&totalQuestions>0) qProgress.textContent=`Question 1 / ${totalQuestions}`;
  if(!pollTimer) pollTimer=setInterval(pollState,1000);
  if(totalQuestions===0){ setTimeout(endSession,2500); }
}

function endSession(){
  if(pollTimer){ clearInterval(pollTimer); pollTimer=null; }
  phase='done';
  const fs=document.createElement('div');
  fs.className='fade-screen';
  fs.textContent=(sessionType==='recap')?'🎉 Recap complete. Thank you!':'🎉 Assessment complete. Thank you!';
  document.body.appendChild(fs);
  owlCenter.style.transition='opacity 3s ease';
  owlCenter.style.opacity='0';
  setTimeout(()=>fs.classList.add('show'),50);
  setTimeout(()=>{ window.close(); },5000);
}

function pollState(){
  fetch(`get_student_view_state.php?session_id=<?= $session_id ?>&_=${Date.now()}`,{cache:'no-store'})
  .then(r=>r.json())
  .then(s=>{
    if(!s)return;
    const idx=Number(s.current_q_index??0),fb=String(s.feedback??'none');
    if(idx!==lastIndex){
      lastIndex=idx; clearFeedback(); $(letsAnswer).stop(true,true).fadeIn(200);
      if(qProgress&&totalQuestions>0) qProgress.textContent=`Question ${Math.min(idx+1,totalQuestions)} / ${totalQuestions}`;
    }
    const isLast=(totalQuestions>0&&idx===totalQuestions-1);
    if((fb==='correct'||fb==='wrong')&&!indexAnimated.has(idx)){
      indexAnimated.add(idx); $(letsAnswer).fadeOut(150);
      owlCenter.style.animation='wiggleCenter .5s ease';
      setTimeout(()=>{ owlCenter.style.animation=''; },550);
      if(fb==='correct') showCorrect(); else showWrong();
      setTimeout(()=>{
        clearFeedback();
        if(isLast){ endSession(); } else { $(letsAnswer).fadeIn(200); }
      }, ANIM_DURATION);
    }
  })
  .catch(()=>{ /* ignore polling errors to avoid UI flicker */ });
}

function tryAutoPlayMuted(v){
  if(!v) return;
  v.muted=true;
  v.play().catch(()=>{ /* autoplay might be blocked; user can click */ });
}

const v=document.getElementById('storybookVideo');
if(v){
  tryAutoPlayMuted(v);
  v.addEventListener('ended', startFlySequence);
}else{
  // If no video (or missing), still run the sequence
  startFlySequence();
}
</script>
</body>
</html>
