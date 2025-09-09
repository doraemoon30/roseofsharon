<!-- footer.php -->
<footer class="footer-area text-center p-4 mt-auto">
  <p class="footer-text">
    © <?= date("Y") ?> ROSE OF SHARON CHILD DEVELOPMENT CENTER. All rights reserved.
  </p>
</footer>


<?php
// Safely fetch flash messages
$__flashes = [];
if (function_exists('take_flashes')) {
  $__flashes = take_flashes();
} else {
  // Fallback: try to load flash helpers if not already available
  $flashPath = __DIR__ . '/flash.php';
  if (file_exists($flashPath)) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once $flashPath;
    if (function_exists('take_flashes')) {
      $__flashes = take_flashes();
    }
  }
}
?>

<!-- Toast container (top-right) -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:1080">
  <div id="toast-stack"></div>
</div>

<?php if (!empty($__flashes)): ?>
  <script>
    // Pass flashes to JS (safe JSON encoding)
    window.__FLASHES__ = <?= json_encode($__flashes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
<?php endif; ?>

<!-- Bootstrap 5 bundle (safe to include even if already loaded) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {
  if (!window.__FLASHES__ || !window.__FLASHES__.length) return;

  const COLORS = {
    success: 'text-bg-success',
    info:    'text-bg-info',
    primary: 'text-bg-primary',
    warning: 'text-bg-warning',
    danger:  'text-bg-danger'
  };

  const stack = document.getElementById('toast-stack');

  window.__FLASHES__.forEach(msg => {
    const cls = COLORS[msg.type] || COLORS.primary;

    // Outer toast
    const toast = document.createElement('div');
    toast.className = `toast align-items-center ${cls} border-0 mb-2`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    toast.dataset.bsDelay = '4000';

    // Inner structure
    const inner = document.createElement('div');
    inner.className = 'd-flex';

    const body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = String(msg.text || ''); // XSS-safe

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close btn-close-white me-2 m-auto';
    closeBtn.setAttribute('data-bs-dismiss', 'toast');
    closeBtn.setAttribute('aria-label', 'Close');

    inner.appendChild(body);
    inner.appendChild(closeBtn);
    toast.appendChild(inner);
    stack.appendChild(toast);

    const t = new bootstrap.Toast(toast);
    toast.addEventListener('hidden.bs.toast', () => toast.remove()); // tidy DOM
    t.show();
  });
})();
</script>
