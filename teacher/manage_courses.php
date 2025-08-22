<?php
// teacher/manage_courses.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['teacher']);

$uid = current_user_id();

// --- Handle actions with PRG to avoid duplicate submissions ---
$status_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    if ($title !== '') {
        $ins = $conn->prepare("INSERT INTO courses (title, description, teacher_id) VALUES (?,?,?)");
        if ($ins) {
            $ins->bind_param("ssi", $title, $desc, $uid);
            $ok = $ins->execute();
            $ins->close();
            header("Location: " . $_SERVER['PHP_SELF'] . "?status=" . ($ok ? "created" : "create_error"));
            exit();
        } else {
            header("Location: " . $_SERVER['PHP_SELF'] . "?status=create_error");
            exit();
        }
    } else {
        header("Location: " . $_SERVER['PHP_SELF'] . "?status=title_required");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $cid = (int)($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    if ($cid > 0 && $title !== '') {
        $u = $conn->prepare("UPDATE courses SET title=?, description=? WHERE id=? AND teacher_id=?");
        if ($u) {
            $u->bind_param("ssii", $title, $desc, $cid, $uid);
            $ok = $u->execute();
            $u->close();
            header("Location: " . $_SERVER['PHP_SELF'] . "?status=" . ($ok ? "updated" : "update_error"));
            exit();
        } else {
            header("Location: " . $_SERVER['PHP_SELF'] . "?status=update_error");
            exit();
        }
    } else {
        header("Location: " . $_SERVER['PHP_SELF'] . "?status=update_invalid");
        exit();
    }
}

if (isset($_GET['delete'])) {
    $cid = (int)$_GET['delete'];
    if ($cid > 0) {
        $d = $conn->prepare("DELETE FROM courses WHERE id=? AND teacher_id=?");
        if ($d) {
            $d->bind_param("ii", $cid, $uid);
            $ok = $d->execute();
            $d->close();
            header("Location: " . $_SERVER['PHP_SELF'] . "?status=" . ($ok ? "deleted" : "delete_error"));
            exit();
        } else {
            header("Location: " . $_SERVER['PHP_SELF'] . "?status=delete_error");
            exit();
        }
    }
}

// Show user-friendly status messages (from PRG)
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'created': $status_msg = "<div class='alert alert-success small'>Course created successfully.</div>"; break;
        case 'updated': $status_msg = "<div class='alert alert-success small'>Course updated successfully.</div>"; break;
        case 'deleted': $status_msg = "<div class='alert alert-warning small'>Course deleted.</div>"; break;
        case 'title_required': $status_msg = "<div class='alert alert-danger small'>Title is required.</div>"; break;
        case 'create_error': $status_msg = "<div class='alert alert-danger small'>Failed to create course.</div>"; break;
        case 'update_error': $status_msg = "<div class='alert alert-danger small'>Failed to update course.</div>"; break;
        case 'delete_error': $status_msg = "<div class='alert alert-danger small'>Failed to delete course.</div>"; break;
        default: $status_msg = ""; break;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title>Manage Courses · MoodleClone</title>

  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{ --brand:#6366f1; --accent:#06b6d4; --muted:#6b7280; --maxw:440px; --card-radius:12px; }
    html,body{height:100%;}
    body{
      margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial; background:linear-gradient(180deg,#f7fbff,#eef6ff);
      -webkit-font-smoothing:antialiased;
      color:#071033;
    }

    /* Remove underline from all links and ensure focus is visible for accessibility */
    a, a:link, a:visited, a:hover, a:active, a:focus {
      text-decoration: none !important;
    }
    /* Keep keyboard focus visible */
    a:focus, button:focus {
      outline: 3px solid rgba(99,102,241,0.18);
      outline-offset: 3px;
    }

    /* Header */
    header.page-header {
      display:flex; align-items:center; justify-content:space-between; gap:8px;
      padding:12px; background:linear-gradient(90deg,var(--brand),var(--accent)); color:#fff;
      box-shadow:0 10px 28px rgba(2,6,23,0.08);
      position:sticky; top:0; z-index:40;
    }
    .btn-back {
      display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,0.12);
      color:#fff; border:0; padding:8px 10px; border-radius:10px; font-weight:700;
    }
    .header-title { text-align:center; flex:1; pointer-events:none; }
    .header-title h1 { margin:0; font-size:16px; font-weight:800; }
    .header-actions { display:flex; gap:8px; align-items:center; }

    /* Page container */
    main.wrapper { max-width:var(--maxw); margin:12px auto; padding:0 12px 120px; width:100%; }

    .card {
      border-radius:var(--card-radius); background:linear-gradient(180deg,#fff,#fbfdff);
      box-shadow:0 12px 36px rgba(2,6,23,0.06);
    }
    .form-control, .btn { border-radius:10px; }

    .create-card .card-header { background:linear-gradient(90deg,var(--brand),var(--accent)); color:#fff; font-weight:800; }
    .course-card { padding:12px; margin-bottom:12px; }
    .course-title { font-weight:800; font-size:1rem; margin:0 0 4px; }
    .course-desc { color:var(--muted); font-size:13px; margin:0 0 8px; line-height:1.35; }

    .actions-row { display:flex; gap:8px; margin-top:8px; }
    .action-btn { flex:1; padding:10px 8px; border-radius:10px; font-weight:800; font-size:14px; }

    details summary { list-style:none; cursor:pointer; }
    .collapse-edit { margin-top:8px; }

    footer.bottom-nav { position:fixed; left:50%; transform:translateX(-50%); bottom:12px; width:calc(100% - 28px); max-width:var(--maxw);
      display:flex; gap:8px; justify-content:space-between; padding:8px; background:rgba(255,255,255,0.98); border-radius:14px; box-shadow:0 12px 36px rgba(2,6,23,0.06); z-index:50;
    }
    .nav-item { flex:1; text-align:center; font-weight:700; color:#071033; padding:8px 6px; border-radius:8px; }

    @media(max-width:360px) {
      .header-title h1 { font-size:15px; }
      .action-btn { font-size:13px; padding:8px; }
    }
  </style>
</head>
<body>

<header class="page-header" role="banner">
  <a href="/dashboard.php" class="btn-back" aria-label="Back to dashboard">
    <i class="bi bi-arrow-left-short" style="font-size:20px;"></i> Back
  </a>

  <div class="header-title" aria-hidden="true">
    <h1>Manage Courses</h1>
    <div style="font-size:12px; opacity:.95;">Teacher dashboard</div>
  </div>

  <div class="header-actions" aria-hidden="true">
    <a href="/profile.php" class="btn-back" style="padding:8px;"><i class="bi bi-person-circle" style="font-size:18px;"></i></a>
    <form method="post" action="/logout.php" style="display:inline;">
      <button class="btn-back" style="padding:8px;"><i class="bi bi-box-arrow-right" style="font-size:16px;"></i></button>
    </form>
  </div>
</header>

<main class="wrapper" role="main" aria-live="polite">

  <!-- messages -->
  <?php if ($status_msg): ?>
    <div class="mb-2"><?php echo $status_msg; ?></div>
  <?php endif; ?>

  <!-- create -->
  <section class="card create-card mb-3">
    <div class="card-header p-3">
      <div class="d-flex justify-content-between align-items-center">
        <div>Create New Course</div>
        <div class="small-muted" style="color:rgba(255,255,255,0.9); font-weight:600;"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></div>
      </div>
    </div>
    <div class="card-body p-3">
      <form method="post" class="row g-2">
        <input type="hidden" name="create" value="1">
        <div class="col-12">
          <input name="title" class="form-control form-control-sm" placeholder="Course Title" required>
        </div>
        <div class="col-12">
          <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Short description (optional)"></textarea>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-success w-100">+ Create Course</button>
        </div>
      </form>
    </div>
  </section>

  <!-- courses list -->
  <h3 style="margin-bottom:8px;">My Courses</h3>

  <?php
  $q = $conn->prepare("SELECT * FROM courses WHERE teacher_id=? ORDER BY id DESC");
  $q->bind_param("i", $uid);
  $q->execute();
  $r = $q->get_result();
  if ($r->num_rows === 0): ?>
    <div class="card course-card">
      <div class="card-body">
        <p class="course-desc">You haven't created any courses yet. Tap the <strong>Create Course</strong> button above to add your first course.</p>
        <a href="#create" class="btn btn-primary w-100">Create my first course</a>
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <?php while($c = $r->fetch_assoc()):
        $cid = (int)$c['id'];
        $editId = "edit-{$cid}";
    ?>
      <div class="col-12">
        <article class="card course-card" aria-labelledby="course-<?php echo $cid; ?>">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div style="flex:1; margin-right:12px;">
                <h4 id="course-<?php echo $cid; ?>" class="course-title text-truncate"><?php echo htmlspecialchars($c['title']); ?></h4>
                <p class="course-desc text-truncate" style="max-height:3.6em;"><?php echo nl2br(htmlspecialchars($c['description'])); ?></p>
              </div>
              <div style="min-width:90px; text-align:right;">
                <a href="/course_view.php?id=<?php echo $cid; ?>" class="btn btn-outline-primary action-btn" style="padding:8px 10px; font-size:13px;">Open</a>
              </div>
            </div>

            <div class="actions-row" style="margin-top:10px;">
              <!-- Edit toggle (Bootstrap collapse) -->
              <button class="btn btn-secondary action-btn" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $editId; ?>" aria-expanded="false" aria-controls="<?php echo $editId; ?>">
                <i class="bi bi-pencil-square"></i> Edit
              </button>

              <a href="?delete=<?php echo $cid; ?>" class="btn btn-danger action-btn" onclick="return confirm('Delete course & all associated data?');">
                <i class="bi bi-trash"></i> Delete
              </a>
            </div>

            <!-- edit collapse -->
            <div class="collapse collapse-edit mt-2" id="<?php echo $editId; ?>">
              <div class="card mt-2 p-2">
                <form method="post" class="row g-2">
                  <input type="hidden" name="update" value="1">
                  <input type="hidden" name="course_id" value="<?php echo $cid; ?>">
                  <div class="col-12">
                    <input name="title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($c['title']); ?>" required>
                  </div>
                  <div class="col-12">
                    <textarea name="description" class="form-control form-control-sm" rows="2"><?php echo htmlspecialchars($c['description']); ?></textarea>
                  </div>
                  <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Save</button>
                    <button type="button" class="btn btn-outline-secondary w-100" data-bs-toggle="collapse" data-bs-target="#<?php echo $editId; ?>">Cancel</button>
                  </div>
                </form>
              </div>
            </div>

          </div>
        </article>
      </div>
    <?php endwhile; ?>
  </div>

</main>

<footer class="bottom-nav" aria-hidden="true">
  <a class="nav-item" href="/dashboard.php"><i class="bi bi-house"></i><div>Home</div></a>
  
  <a class="nav-item" href="/teacher/announcements.php"><i class="bi bi-bell"></i><div>Announce</div></a>
  <a class="nav-item" href="/profile.php"><i class="bi bi-person"></i><div>Profile</div></a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
