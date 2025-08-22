<?php
// /profile.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// If your auth has a generic gatekeeper, use it; otherwise ensure logged-in
if (!function_exists('current_user_id')) {
  die("Auth helper missing.");
}
$current_user_id = (int) current_user_id();
if ($current_user_id <= 0) {
  header("Location: /login.php");
  exit();
}

// Create table once (idempotent)
$conn->query("
  CREATE TABLE IF NOT EXISTS user_profiles (
    user_id INT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    department VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$alert = "";
$profile = [
  'full_name' => '',
  'department' => '',
  'email' => '',
  'photo_path' => null
];

// Load existing profile
$stmt = $conn->prepare("SELECT full_name, department, email, photo_path FROM user_profiles WHERE user_id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) $profile = $row;
$stmt->close();

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function ensure_upload_dir($path){
  if (!is_dir($path)) @mkdir($path, 0775, true);
  if (!is_dir($path) || !is_writable($path)) throw new RuntimeException("Upload directory not writable: $path");
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full_name  = trim($_POST['full_name'] ?? '');
  $department = trim($_POST['department'] ?? '');
  $email      = trim($_POST['email'] ?? '');
  $delete_photo = isset($_POST['delete_photo']) ? true : false;

  // Basic validation
  $errors = [];
  if ($full_name === '')   $errors[] = "Full name is required.";
  if ($department === '')  $errors[] = "Department is required.";
  if ($email === '')       $errors[] = "Gmail address is required.";
  // Enforce Gmail as requested
  if ($email !== '' && !preg_match('/@gmail\.com$/i', $email)) {
    $errors[] = "Please enter a Gmail address ending with @gmail.com.";
  }

  // Image handling
  $uploads_rel = '/uploads/profile_photos';
  $uploads_abs = __DIR__ . $uploads_rel;
  $new_photo_rel = null; // store relative web path
  $old_photo_rel = $profile['photo_path'];

  try {
    if ($delete_photo) {
      // mark for deletion after successful DB write
      $new_photo_rel = null;
    } elseif (!empty($_FILES['photo']) && is_array($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
      $err = $_FILES['photo']['error'];
      if ($err !== UPLOAD_ERR_OK) {
        $errors[] = "Photo upload failed (code $err).";
      } else {
        // Validate file
        $tmp = $_FILES['photo']['tmp_name'];
        $size = (int)$_FILES['photo']['size'];
        if ($size > 2 * 1024 * 1024) { // 2MB
          $errors[] = "Photo is too large (max 2 MB).";
        } else {
          $finfo = new finfo(FILEINFO_MIME_TYPE);
          $mime = $finfo->file($tmp);
          $ext = null;
          switch ($mime) {
            case 'image/jpeg': $ext = 'jpg'; break;
            case 'image/png':  $ext = 'png'; break;
            case 'image/webp': $ext = 'webp'; break;
            default:
              $errors[] = "Only JPG, PNG, or WEBP images are allowed.";
          }
          if (!$errors) {
            ensure_upload_dir($uploads_abs);
            $filename = 'u' . $current_user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest_abs = $uploads_abs . '/' . $filename;
            if (!move_uploaded_file($tmp, $dest_abs)) {
              $errors[] = "Could not save uploaded photo.";
            } else {
              // lock down permissions a bit
              @chmod($dest_abs, 0644);
              $new_photo_rel = $uploads_rel . '/' . $filename;
            }
          }
        }
      }
    }
  } catch (Throwable $e) {
    $errors[] = "Upload error: " . $e->getMessage();
  }

  if (!$errors) {
    // Upsert profile
    // Use photo: if new upload, use new; else if delete flag, set NULL; else keep old
    $final_photo_rel = $delete_photo ? null : ($new_photo_rel ?: $old_photo_rel);

    // INSERT ... ON DUPLICATE KEY UPDATE
    $sql = "
      INSERT INTO user_profiles (user_id, full_name, department, email, photo_path)
      VALUES (?, ?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        full_name = VALUES(full_name),
        department = VALUES(department),
        email = VALUES(email),
        photo_path = VALUES(photo_path)
    ";
    $st = $conn->prepare($sql);
    $st->bind_param("issss", $current_user_id, $full_name, $department, $email, $final_photo_rel);
    if ($st->execute()) {
      $st->close();

      // Also sync users.name so the rest of the app shows updated name
      if ($full_name !== '') {
        if ($upd = $conn->prepare("UPDATE users SET name=? WHERE id=?")) {
          $upd->bind_param("si", $full_name, $current_user_id);
          $upd->execute();
          $upd->close();
        }
      }

      // If we saved a new photo successfully, and there was an old photo, remove the old file
      if ($new_photo_rel && $old_photo_rel && $old_photo_rel !== $new_photo_rel) {
        $old_abs = __DIR__ . $old_photo_rel;
        if (is_file($old_abs)) @unlink($old_abs);
      }
      // If delete requested and there was an old photo, remove it
      if ($delete_photo && $old_photo_rel) {
        $old_abs = __DIR__ . $old_photo_rel;
        if (is_file($old_abs)) @unlink($old_abs);
      }

      header("Location: /profile.php?saved=1");
      exit();
    } else {
      $alert = "<div class='alert alert-danger small'>Failed to save: " . h($st->error) . "</div>";
      $st->close();
    }
  } else {
    $alert = "<div class='alert alert-danger small'>" . implode("<br>", array_map('h', $errors)) . "</div>";
    // keep inputs in form
    $profile['full_name']  = $full_name;
    $profile['department'] = $department;
    $profile['email']      = $email;
    if ($new_photo_rel) $profile['photo_path'] = $new_photo_rel;
  }
}

if (isset($_GET['saved']) && (int)$_GET['saved'] === 1) {
  $alert = "<div class='alert alert-success small'>Profile updated.</div>";
}

// Helper for avatar initials
function initials($str) {
  $s = trim($str);
  if (!$s) return 'U';
  $parts = preg_split('/\s+/', $s);
  if (count($parts) === 1) return strtoupper(substr($parts[0],0,1));
  return strtoupper(substr($parts[0],0,1) . substr(end($parts),0,1));
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover" />
  <title>My Profile · MoodleClone</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --brand:#6366f1; --accent:#06b6d4; --muted:#6b7280; --card-radius:12px; --maxw:540px;
    }
    body{
      margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial;
      background: linear-gradient(180deg,#f7fbff,#eef2ff);
      color:#071033; -webkit-font-smoothing:antialiased;
    }
    a, a:hover, a:focus, a:active, a:visited { text-decoration: none !important; text-decoration-skip-ink: auto; }
    a:focus-visible, button:focus-visible, .btn:focus-visible {
      outline: 2px solid rgba(99,102,241,.65); outline-offset: 2px; box-shadow: 0 0 0 4px rgba(99,102,241,.15);
    }
    header.appbar {
      display:flex; align-items:center; justify-content:space-between; gap:10px;
      padding:12px; background:linear-gradient(90deg,var(--brand),var(--accent)); color:#fff;
      box-shadow:0 10px 30px rgba(2,6,23,0.08); position:sticky; top:0; z-index:50;
    }
    .btn-back {
      display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,0.12);
      color:#fff; border:0; padding:8px 10px; border-radius:10px; font-weight:700;
    }
    main.container { max-width:var(--maxw); margin:16px auto 120px; padding:0 12px; }
    .card { border-radius:var(--card-radius); background:#fff; padding:12px; box-shadow:0 10px 30px rgba(2,6,23,0.06); margin-bottom:12px; }
    .avatar {
      width:88px; height:88px; border-radius:16px; display:flex; align-items:center; justify-content:center;
      font-weight:800; color:#fff; background:linear-gradient(90deg,var(--brand),var(--accent)); font-size:28px;
    }
    .preview-img {
      width:88px; height:88px; border-radius:16px; object-fit:cover; object-position:center; border:1px solid #e5e7eb;
    }
  </style>
</head>
<body>

<header class="appbar">
  <a class="btn-back" href="/dashboard.php"><i class="bi bi-arrow-left-short" style="font-size:18px;"></i> Back</a>
  <div style="text-align:center; flex:1;">
    <strong>My Profile</strong>
  </div>
  <span class="btn-back" style="opacity:.0001; pointer-events:none;">.</span>
</header>

<main class="container" role="main" aria-live="polite">
  <?php if (!empty($alert)) echo $alert; ?>

  <div class="card">
    <div class="d-flex align-items-center gap-3 mb-2">
      <?php if (!empty($profile['photo_path'])): ?>
        <img src="<?php echo h($profile['photo_path']); ?>" alt="Profile photo" class="preview-img">
      <?php else: ?>
        <div class="avatar" aria-hidden="true"><?php echo h(initials($profile['full_name'] ?: 'User')); ?></div>
      <?php endif; ?>
      <div>
        <div class="text-muted" style="font-size:.9rem;">Update your information and photo</div>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data" autocomplete="off">
      <div class="mb-2">
        <label class="form-label">Full Name</label>
        <input type="text" name="full_name" class="form-control" maxlength="120" placeholder="Your full name" value="<?php echo h($profile['full_name']); ?>" required>
      </div>

      <div class="mb-2">
        <label class="form-label">Department</label>
        <input type="text" name="department" class="form-control" maxlength="120" placeholder="Your department" value="<?php echo h($profile['department']); ?>" required>
      </div>

      <div class="mb-2">
        <label class="form-label">Gmail</label>
        <input type="email" name="email" class="form-control" maxlength="190" placeholder="you@gmail.com" value="<?php echo h($profile['email']); ?>" required>
        <div class="form-text">Must end with <code>@gmail.com</code>.</div>
      </div>

      <div class="mb-2">
        <label class="form-label">Profile Picture (JPG/PNG/WEBP, max 2MB)</label>
        <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
        <?php if (!empty($profile['photo_path'])): ?>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="delete_photo" id="delete_photo">
            <label class="form-check-label" for="delete_photo">Remove current photo</label>
          </div>
        <?php endif; ?>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
      </div>
    </form>
  </div>
</main>

<nav class="position-fixed bottom-0 start-50 translate-middle-x mb-3" style="width:calc(100% - 28px); max-width:540px;">
  <div class="d-flex gap-2 rounded-4 shadow-lg p-2 bg-white">
    <a class="btn btn-light flex-fill" href="/dashboard.php"><i class="bi bi-house"></i> Home</a>
    <a class="btn btn-light flex-fill" href="/teacher/manage_courses.php"><i class="bi bi-journal"></i> Manage</a>
    <a class="btn btn-primary flex-fill" href="/profile.php"><i class="bi bi-person"></i> Profile</a>
  </div>
</nav>

</body>
</html>
