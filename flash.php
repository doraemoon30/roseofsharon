<?php
if (!isset($_SESSION)) session_start();

if (!function_exists('flash')) {
  function flash(string $type, string $text): void {
    $_SESSION['flash'][] = ['type'=>$type,'text'=>$text];
  }
}
if (!function_exists('set_flash_message')) {
  // Alias so older calls keep working
  function set_flash_message(string $type, string $text): void {
    flash($type, $text);
  }
}
if (!function_exists('take_flashes')) {
  function take_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
  }
}

$__flashes = take_flashes();
if (!empty($__flashes)) {
  $safe = array_map(function($m){
    $t = $m['type'] ?? 'primary';
    $allowed = ['success','info','primary','warning','danger'];
    if (!in_array($t, $allowed, true)) $t = 'primary';
    return ['type'=>$t, 'text'=>(string)($m['text'] ?? '')];
  }, $__flashes);

  echo '<style>
  .flash-container{
    position:fixed; top:20px; left:50%; transform:translateX(-50%);
    z-index:2000; display:flex; flex-direction:column; align-items:center; gap:10px;
    pointer-events:none;
  }
  .flash-message{
    pointer-events:auto;
    font-size:1.25rem; padding:15px 25px; border-radius:10px; font-weight:700; text-align:center;
    box-shadow:0 5px 20px rgba(0,0,0,.25); transition:opacity .4s ease, transform .4s ease;
  }
  .flash-message.success{background:#28a745;color:#fff}
  .flash-message.info,.flash-message.primary{background:#0d6efd;color:#fff}
  .flash-message.warning{background:#ffc107;color:#111}
  .flash-message.danger{background:#dc3545;color:#fff}
  .flash-hide{opacity:0; transform:translateY(-10px)}
  </style>';

  echo '<div class="flash-container" id="flash-container" role="status" aria-live="polite">';
  foreach ($safe as $m){
    $type = htmlspecialchars($m['type'], ENT_QUOTES, 'UTF-8');
    $text = htmlspecialchars($m['text'], ENT_QUOTES, 'UTF-8');
    echo '<div class="flash-message '.$type.'">'.$text.'</div>';
  }
  echo '</div>';

  echo '<script>
  (function(){
    const container = document.getElementById("flash-container");
    if (!container) return;
    const msgs = Array.from(container.querySelectorAll(".flash-message"));
    msgs.forEach((msg, i) => {
      setTimeout(() => {
        msg.classList.add("flash-hide");
        setTimeout(() => {
          msg.remove();
          if (container && container.children.length === 0) container.remove();
        }, 400);
      }, 5000 + i*200);
    });
  })();
  </script>';
}
