<?php
// register.php — Mobile-first Professional Registration (Bootstrap, CSRF, validation)
session_start();
require_once __DIR__ . '/includes/db.php';

// Use auth helpers if available
if (file_exists(__DIR__ . '/includes/auth.php')) {
    require_once __DIR__ . '/includes/auth.php';
}

// Fallback CSRF helpers
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

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$old = ['name'=>'','email'=>'','role'=>'student'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!check_csrf($_POST['csrf'] ?? '')) {
        $errors[] = "Session expired or invalid token. Please refresh and try again.";
    } else {
        // Collect and sanitize inputs
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pwd   = $_POST['password'] ?? '';
        $pwd2  = $_POST['confirm_password'] ?? '';
        $role  = $_POST['role'] ?? 'student';
        $agree = isset($_POST['agree']);

        $old['name'] = $name;
        $old['email'] = $email;
        $old['role'] = $role;

        // Server-side validation
        if ($name === '' || mb_strlen($name) < 2) {
            $errors[] = "Please enter your full name (at least 2 characters).";
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please provide a valid email address.";
        }
        if ($pwd === '' || strlen($pwd) < 8) {
            $errors[] = "Password must be at least 8 characters.";
        }
        // simple strength: letters + numbers; require at least one letter and one digit
        if (!preg_match('/[A-Za-z]/', $pwd) || !preg_match('/\d/', $pwd)) {
            $errors[] = "Password should contain at least one letter and one number.";
        }
        if ($pwd !== $pwd2) {
            $errors[] = "Password and confirm password do not match.";
        }
        if (!in_array($role, ['student','teacher'], true)) {
            $errors[] = "Invalid role selected.";
        }
        if (!$agree) {
            $errors[] = "You must accept the terms and privacy policy to register.";
        }

        // Check duplicate email only if no earlier errors
        if (empty($errors)) {
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ?");
            if ($chk === false) {
                $errors[] = "Database error (prepare failed).";
            } else {
                $chk->bind_param("s", $email);
                $chk->execute();
                $exists = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($exists) {
                    $errors[] = "An account with that email already exists. Try signing in or resetting your password.";
                }
            }
        }

        // Insert user
        if (empty($errors)) {
            $password_hash = password_hash($pwd, PASSWORD_BCRYPT);
            $ins = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
            if ($ins === false) {
                $errors[] = "Database error (prepare failed).";
            } else {
                $ins->bind_param("ssss", $name, $email, $password_hash, $role);
                if ($ins->execute()) {
                    // Option A: redirect to login with success flag
                    header("Location: index.php?registered=1");
                    exit();
                } else {
                    $errors[] = "Registration failed: " . htmlspecialchars($ins->error);
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
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover" />
  <title>Create account · MoodleClone</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root { --accent: #f26b21; }
    html,body { height:100%; }
    body { min-height:100dvh; display:flex; align-items:center; justify-content:center; background: linear-gradient(180deg,#f5f7fb 0%, #eef2f7 100%); font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial; }
    .card-panel { width:100%; max-width:520px; border-radius:14px; box-shadow:0 10px 30px rgba(15,23,42,.06); overflow:hidden; background:#fff; }
    .topbar { background: linear-gradient(90deg, rgba(242,107,33,0.12), rgba(6,182,212,0.06)); padding:18px; display:flex; gap:12px; align-items:center; }
    .logo { width:44px; height:44px; border-radius:10px; background:var(--accent); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:18px; }
    .card-body { padding:22px; }
    .form-control, .form-select { border-radius:10px; padding:11px 12px; font-size:15px; }
    .btn-accent { background:var(--accent); border-color:var(--accent); border-radius:10px; padding:12px 14px; font-weight:600; }
    .small-muted { color:#6b7280; font-size:.95rem; }
    .pw-meter { height:6px; border-radius:6px; background:#e9ecef; overflow:hidden; }
    .pw-meter > i { display:block; height:100%; width:0%; background:linear-gradient(90deg,#f97316,#06b6d4); transition: width .25s ease; }
    @media (max-width:420px) {
      .card-body { padding:16px; }
    }
  </style>
</head>
<body>
  <main class="card-panel" role="main" aria-labelledby="createHeading">
    <div class="topbar">
      <div class="logo" aria-hidden="true">MC</div>
      <div>
        <h1 id="createHeading" style="margin:0;font-size:16px;">Create your account</h1>
        <div class="small-muted">Join as a student or teacher</div>
      </div>
    </div>

    <div class="card-body">
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert" aria-live="polite">
          <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
              <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" class="needs-validation" novalidate autocomplete="on" aria-describedby="regHelp">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">

        <div class="mb-3">
          <label for="name" class="form-label small-muted">Full name</label>
          <input id="name" name="name" type="text" class="form-control" placeholder="Your full name" required value="<?php echo htmlspecialchars($old['name']); ?>">
          <div class="invalid-feedback">Please enter your full name.</div>
        </div>

        <div class="mb-3">
          <label for="email" class="form-label small-muted">School email</label>
          <input id="email" name="email" type="email" class="form-control" placeholder="you@school.edu" required inputmode="email" autocomplete="email" value="<?php echo htmlspecialchars($old['email']); ?>">
          <div class="invalid-feedback">Please enter a valid email address.</div>
        </div>

        <div class="row g-2">
          <div class="col-12 col-md-6 mb-3">
            <label for="password" class="form-label small-muted">Password</label>
            <div class="input-group">
              <input id="password" name="password" type="password" class="form-control" placeholder="Create password" required autocomplete="new-password" aria-describedby="pwHelp">
              <button type="button" class="btn btn-outline-secondary" id="togglePwd" aria-label="Show password">Show</button>
            </div>
            <div class="form-text small-muted" id="pwHelp">Min 8 chars — include letters & numbers.</div>
            <div class="pw-meter mt-2" aria-hidden="true"><i id="pwBar"></i></div>
            <div class="invalid-feedback">Please enter a password (min 8 chars).</div>
          </div>

          <div class="col-12 col-md-6 mb-3">
            <label for="confirm_password" class="form-label small-muted">Confirm password</label>
            <input id="confirm_password" name="confirm_password" type="password" class="form-control" placeholder="Repeat password" required autocomplete="new-password">
            <div class="invalid-feedback">Passwords do not match.</div>
          </div>
        </div>

        <div class="mb-3">
          <label for="role" class="form-label small-muted">I want to join as</label>
          <select id="role" name="role" class="form-select" required>
            <option value="student" <?php echo $old['role']=='student' ? 'selected' : ''; ?>>Student</option>
            <option value="teacher" <?php echo $old['role']=='teacher' ? 'selected' : ''; ?>>Teacher</option>
            <option value="admin" <?php echo $old['role']=='admin' ? 'selected' : ''; ?>>admin</option>
          </select>
        </div>

        <div class="mb-3 form-check">
          <input id="agree" name="agree" class="form-check-input" type="checkbox" required>
          <label for="agree" class="form-check-label small-muted">I agree to the <a href="#" class="link-primary">Terms</a> & <a href="#" class="link-primary">Privacy Policy</a>.</label>
          <div class="invalid-feedback">You must accept the terms to continue.</div>
        </div>

        <div class="d-grid mb-3">
          <button id="submitBtn" type="submit" class="btn btn-accent btn-lg" aria-label="Create account">Create account</button>
        </div>

        <div id="regHelp" class="small-muted text-center">
          Already have an account? <a href="index.php" class="link-primary">Sign in</a>
        </div>
      </form>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Password strength meter (very small heuristic)
    (function(){
      const pw = document.getElementById('password');
      const pwBar = document.getElementById('pwBar');
      const confirm = document.getElementById('confirm_password');
      const submitBtn = document.getElementById('submitBtn');

      function strengthScore(s) {
        let score = 0;
        if (!s) return 0;
        if (s.length >= 8) score += 1;
        if (/[a-z]/.test(s) && /[A-Z]/.test(s)) score += 1;
        if (/\d/.test(s)) score += 1;
        if (/[^A-Za-z0-9]/.test(s)) score += 1;
        return Math.min(score, 4);
      }

      function updateMeter() {
        const score = strengthScore(pw.value);
        const pct = (score / 4) * 100;
        pwBar.style.width = pct + '%';
        // color gradient by width
        if (pct < 35) pwBar.style.background = '#f87171';
        else if (pct < 70) pwBar.style.background = '#f59e0b';
        else pwBar.style.background = '#10b981';
      }

      pw.addEventListener('input', updateMeter);

      // Show / hide password
      const toggle = document.getElementById('togglePwd');
      toggle.addEventListener('click', () => {
        const hidden = pw.type === 'password';
        pw.type = hidden ? 'text' : 'password';
        confirm.type = hidden ? 'text' : 'password';
        toggle.textContent = hidden ? 'Hide' : 'Show';
      });

      // client-side validation: password confirm check before submit
      const form = document.querySelector('form.needs-validation');
      form.addEventListener('submit', function(e){
        // built-in validity
        if (!form.checkValidity()) {
          e.preventDefault();
          e.stopPropagation();
          form.classList.add('was-validated');
          return;
        }
        // confirm password match
        if (pw.value !== confirm.value) {
          e.preventDefault();
          e.stopPropagation();
          confirm.setCustomValidity("Passwords do not match.");
          form.classList.add('was-validated');
          return;
        } else {
          confirm.setCustomValidity("");
        }
        // prevent double submit
        submitBtn.disabled = true;
        submitBtn.innerText = 'Creating account…';
      });
    })();
  </script>
</body>
</html>
