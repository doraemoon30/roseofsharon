<?php 
session_start(); 
require_once 'flash.php'; // FLASH utilities
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login – ROSE OF SHARON</title>

  <!-- FLASH: Bootstrap for toasts -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      height: 100vh;
      background: url('assets/images/sharon.png') no-repeat center center fixed;
      background-size: cover;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
    }

    .business-name {
      font-size: 2.5em;
      color: white;
      font-weight: bold;
      font-family: 'Algerian', sans-serif;
      text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.6);
      letter-spacing: 1px;
      margin-bottom: 20px;
      background-color: transparent;
      padding: 10px 20px;
      border-radius: 10px;
      text-align: center;
    }

    .login-container {
      width: 90%;
      max-width: 400px;
      padding: 30px;
      background: rgba(255, 255, 255, 0.5);
      border-radius: 15px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }

    h1 {
      margin-bottom: 20px;
      color: #1b5299;
      font-size: 1.8em;
      font-weight: bold;
    }

    label {
      display: block;
      margin-bottom: 6px;
      text-align: left;
      font-weight: 600;
      color: #333;
    }

    .input-group { position: relative; }

    input {
      width: 100%;
      padding: 12px;
      margin-bottom: 15px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 16px;
      box-sizing: border-box;
    }

    .toggle-password {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #888;
    }

    button {
      width: 100%;
      padding: 12px;
      background-color: #19647e;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: bold;
      cursor: pointer;
      transition: 0.3s ease;
    }
    button:hover { background-color: #ffd700; color: black; }

    .alert {
      padding: 12px;
      background-color: #fff3cd;
      color: #856404;
      border: 1px solid #ffeeba;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
    }

    .logo-bar {
      margin-top: 50px;
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 20px;
    }
    .logo-bar img {
      height: 70px;
      width: auto;
      object-fit: contain;
      filter: drop-shadow(1px 1px 1px #000);
    }

    p { margin-top: 10px; font-size: 0.9em; color: #555; }

    @media (max-width: 500px) {
      .business-name { font-size: 1.6em; padding: 8px 12px; }
      .logo-bar img { height: 50px; }
    }
  </style>
</head>
<body>

  <!-- FLASH (primary): prefer helper if available -->
  <?php if (function_exists('render_flashes')) { render_flashes(); } ?>

  <div class="business-name">
    LISTEN-IT, ANSWER-IT - <br>
    Interactive Storytelling For Children’s Comprehension at<br>
    Rose of Sharon Child Development Center
  </div>

  <div class="login-container">
    <h1>Login</h1>

    <?php if (isset($_GET['timeout'])): ?>
      <div class="alert">⏳ You have been logged out due to inactivity. Please log in again.</div>
    <?php endif; ?>

    <form action="authenticate.php" method="POST" autocomplete="on">
      <label for="username">Username:</label>
      <input type="text" name="username" id="username" required autocomplete="username" autofocus>

      <label for="password">Password:</label>
      <div class="input-group">
        <input type="password" name="password" id="password" required autocomplete="current-password">
        <i class="fa-solid fa-eye toggle-password" onclick="togglePassword()" aria-label="Show or hide password"></i>
      </div>

      <button type="submit">Login</button>
    </form>
  </div>

  <div class="logo-bar">
    <img src="assets/images/3.png" alt="DepEd">
    <img src="assets/images/4.png" alt="DSWD">
    <img src="assets/images/2.png" alt="City College of Tagaytay">
    <img src="assets/images/1.png" alt="City of Tagaytay">
  </div>

  <script>
    function togglePassword() {
      const password = document.getElementById("password");
      const icon = document.querySelector(".toggle-password");
      if (password.type === "password") {
        password.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      } else {
        password.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      }
    }
  </script>

  <!-- FLASH (fallback): only if render_flashes() is NOT defined -->
  <?php if (!function_exists('render_flashes')): ?>
    <!-- Toast container -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index:1080">
      <div id="toast-stack"></div>
    </div>

    <?php $flashes = function_exists('take_flashes') ? take_flashes() : []; ?>
    <?php if (!empty($flashes)): ?>
      <script>window.__FLASHES__ = <?php echo json_encode($flashes); ?>;</script>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function(){
      if (!window.__FLASHES__ || !window.__FLASHES__.length) return;
      const colors = {
        success:'text-bg-success',
        info:'text-bg-info',
        primary:'text-bg-primary',
        warning:'text-bg-warning',
        danger:'text-bg-danger'
      };
      window.__FLASHES__.forEach(msg=>{
        const type = colors[msg.type] ? msg.type : 'primary';
        const id = 't'+Math.random().toString(36).slice(2);
        const html = `
          <div id="${id}" class="toast align-items-center ${type?colors[type]:''} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
            <div class="d-flex">
              <div class="toast-body">${msg.text}</div>
              <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
          </div>`;
        document.getElementById('toast-stack').insertAdjacentHTML('beforeend', html);
        new bootstrap.Toast(document.getElementById(id)).show();
      });
    })();
    </script>
  <?php endif; ?>
</body>
</html>
