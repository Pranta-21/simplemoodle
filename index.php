<?php
// index.php — Mobile-first Professional Login (Bootstrap, CSRF, throttle)
session_start();
require_once __DIR__ . '/includes/db.php';

// If you have auth helpers, use them (csrf_token / check_csrf). If not, define fallbacks.
if (file_exists(__DIR__ . '/includes/auth.php')) {
    require_once __DIR__ . '/includes/auth.php';
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf'];
    }
}
if (!function_exists('check_csrf')) {
    function check_csrf($token) {
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }
}

// If already logged in, send to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Simple rate limiting (max attempts per window)
$MAX_ATTEMPTS = 6;
$WINDOW_SEC   = 600; // 10 minutes
$_SESSION['login_window_started'] ??= time();
$_SESSION['login_attempts'] ??= 0;

if (time() - $_SESSION['login_window_started'] > $WINDOW_SEC) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_window_started'] = time();
}

$error = "";
$info  = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // throttle check
    if ($_SESSION['login_attempts'] >= $MAX_ATTEMPTS) {
        $remaining = max(0, $WINDOW_SEC - (time() - $_SESSION['login_window_started']));
        $mins = ceil($remaining / 60);
        $error = "Too many attempts. Try again in ~{$mins} minute(s).";
    } elseif (!check_csrf($_POST['csrf'] ?? '')) {
        $error = "Session expired or invalid token. Please refresh and try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif ($password === '') {
            $error = "Please enter your password.";
        } else {
            $sql = "SELECT id, name, email, password, role FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                $error = "Database error (prepare failed).";
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($user && password_verify($password, $user['password'])) {
                    // success
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['role']    = $user['role'] ?? 'student';
                    $_SESSION['login_attempts'] = 0; // reset throttle
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $_SESSION['login_attempts']++;
                    $error = "Invalid email or password.";
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>Sign in · MoodleClone</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root { --accent: #f26b21; } /* warm Moodle-like accent */
    html,body { height:100%; }
    body { min-height:100dvh; display:flex; align-items:center; justify-content:center; background: linear-gradient(180deg,#f5f7fb 0%, #eef2f7 100%); font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; }
    .auth-card { width:100%; max-width:440px; border-radius:14px; box-shadow:0 10px 30px rgba(15,23,42,.08); overflow:hidden; background:#fff; }
    .auth-top { background: linear-gradient(90deg, rgba(242,107,33,0.12), rgba(6,182,212,0.06)); padding:22px; display:flex; gap:12px; align-items:center; }
    .logo { width:44px; height:44px; border-radius:10px; background:var(--accent); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:18px; }
    .auth-body { padding:22px; }
    .form-control { border-radius:10px; padding:12px 14px; font-size:16px; }
    .btn-primary { background:var(--accent); border-color:var(--accent); border-radius:10px; padding:12px 14px; font-weight:600; }
    .btn-primary:active,.btn-primary:focus { box-shadow:0 6px 18px rgba(242,107,33,.15); }
    .small-muted { color:#6b7280; font-size:0.92rem; }
    .link-muted { color:var(--accent); text-decoration:none; font-weight:600; }
    @media (max-width:420px) {
      .auth-card { margin:12px; }
      .auth-top { padding:16px; }
      .auth-body { padding:16px; }
    }
  </style>
</head>
<body>
  <main class="auth-card" role="main" aria-labelledby="loginHeading">
    <div class="auth-top">
      <div class="logo" aria-hidden="true">MC</div>
      <div>
        <h1 id="loginHeading" style="margin:0;font-size:18px;">MoodleClone</h1>
        <div class="small-muted">Secure access to courses, assignments & materials</div>
      </div>
    </div>

    <div class="auth-body">
      <?php if ($error): ?>
        <div class="alert alert-danger" role="alert" aria-live="polite"><?php echo htmlspecialchars($error); ?></div>
      <?php elseif ($info): ?>
        <div class="alert alert-info" role="status"><?php echo htmlspecialchars($info); ?></div>
      <?php endif; ?>

      <form method="post" class="needs-validation" novalidate autocomplete="on" aria-describedby="loginHelp">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">

        <div class="mb-3">
          <label for="email" class="form-label small-muted">Email</label>
          <input
            id="email"
            name="email"
            type="email"
            class="form-control"
            placeholder="you@school.edu"
            required
            inputmode="email"
            autocomplete="email"
            autocapitalize="off"
            spellcheck="false"
          >
          <div class="invalid-feedback">Please enter a valid email.</div>
        </div>

        <div class="mb-3">
          <label for="password" class="form-label small-muted d-flex justify-content-between align-items-center">
            <span>Password</span>
            <a href="forgot_password.php" class="small link-muted">Forgot?</a>
          </label>
          <div class="input-group">
            <input
              id="password"
              name="password"
              type="password"
              class="form-control"
              placeholder="Enter your password"
              required
              autocomplete="current-password"
              enterkeyhint="go"
            >
            <button type="button" class="btn btn-outline-secondary" id="togglePwd" aria-label="Show password" title="Show password">Show</button>
          </div>
          <div class="invalid-feedback">Password is required.</div>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="remember" name="remember">
            <label class="form-check-label small-muted" for="remember">Keep me signed in</label>
          </div>
          <a href="register.php" class="small link-muted">Create account</a>
        </div>

        <div class="d-grid mb-2">
          <button id="submitBtn" type="submit" class="btn btn-primary btn-lg" aria-label="Sign in">Sign in</button>
        </div>

        <div id="loginHelp" class="small-muted text-center">
          By signing in you agree to the <a href="#" class="link-muted">Terms</a> & <a href="#" class="link-muted">Privacy</a>.
        </div>
      </form>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Show/Hide password
    (function(){
      const pwd = document.getElementById('password');
      const btn = document.getElementById('togglePwd');
      if (!pwd || !btn) return;
      btn.addEventListener('click', () => {
        const isHidden = pwd.type === 'password';
        pwd.type = isHidden ? 'text' : 'password';
        btn.textContent = isHidden ? 'Hide' : 'Show';
        btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
      });
    })();

    // Bootstrap form validation + prevent double submit
    (function(){
      'use strict';
      const form = document.querySelector('.needs-validation');
      const submitBtn = document.getElementById('submitBtn');
      form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
          e.preventDefault();
          e.stopPropagation();
          form.classList.add('was-validated');
          return;
        }
        // disable button to prevent double submits
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerText = 'Signing in…';
        }
      }, false);
    })();
  </script>
</body>
</html>
