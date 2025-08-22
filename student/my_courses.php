<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['student']);

$uid = current_user_id();

// fetch enrolled courses
$q = $conn->prepare("SELECT c.id, c.title, c.description, u.name AS teacher 
                     FROM enrollments e 
                     JOIN courses c ON c.id=e.course_id
                     LEFT JOIN users u ON u.id=c.teacher_id
                     WHERE e.user_id=?
                     ORDER BY c.id DESC");
$q->bind_param("i", $uid);
$q->execute();
$r = $q->get_result();
$courses = $r->fetch_all(MYSQLI_ASSOC);
$q->close();

// helper: compute simple progress (if tables exist)
function course_progress($conn, $course_id, $user_id) {
    // returns 0..100
    // safe queries: if tables missing or zero modules -> 0
    $total = 0; $done = 0;
    // total modules
    $t = $conn->prepare("SELECT COUNT(*) AS cnt FROM course_modules WHERE course_id = ?");
    if ($t) {
        $t->bind_param("i", $course_id);
        $t->execute();
        $total = (int)$t->get_result()->fetch_assoc()['cnt'];
        $t->close();
        if ($total > 0) {
            $c = $conn->prepare("SELECT COUNT(*) AS cnt FROM module_completion WHERE module_id IN (SELECT id FROM course_modules WHERE course_id = ?) AND user_id = ? AND completed = 1");
            if ($c) {
                $c->bind_param("ii", $course_id, $user_id);
                $c->execute();
                $done = (int)$c->get_result()->fetch_assoc()['cnt'];
                $c->close();
            }
        }
    }
    if ($total <= 0) return 0;
    return (int) round(($done / $total) * 100);
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover" />
  <title>My Courses · MoodleClone</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    /* Mobile-first layout optimized for ~6" Android */
    :root{ --accent:#6366f1; --muted:#6b7280; --card-radius:12px; }
    html,body{height:100%;}
    body{
      margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial;
      color:#0f172a; background:linear-gradient(180deg,#f8fafc,#eef2ff);
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
      display:flex; flex-direction:column;
    }

    header.page-header{
      display:flex; align-items:center; justify-content:space-between;
      gap:8px; padding:12px; background:linear-gradient(90deg,var(--accent), #06b6d4);
      color:#fff; box-shadow:0 6px 20px rgba(2,6,23,0.08);
    }
    .btn-back{
      display:inline-flex; align-items:center; gap:8px;
      background:rgba(255,255,255,0.12); color:#fff; border:0; padding:8px 10px; border-radius:10px;
      font-weight:600;
    }
    .header-title{ font-size:16px; font-weight:700; text-align:center; flex:1; }
    .profile-icon{
      width:38px; height:38px; border-radius:10px; background:rgba(255,255,255,0.12);
      display:flex; align-items:center; justify-content:center; font-weight:700;
    }

    main.container{
      max-width:440px; margin:12px auto; width:100%; padding:0 12px 84px; /* leave space for bottom nav */
      flex:1 1 auto;
    }

    .hero {
      background:#fff; border-radius:var(--card-radius); padding:12px; box-shadow:0 8px 24px rgba(2,6,23,0.04);
      display:flex; gap:12px; align-items:center; margin-bottom:12px;
    }
    .hero .greet { font-weight:700; font-size:16px; }
    .hero .sub { color:var(--muted); font-size:13px; margin-top:4px; }

    .course-card{
      background:linear-gradient(180deg,#ffffff,#fbfdff); border-radius:var(--card-radius);
      padding:12px; margin-bottom:10px; box-shadow:0 8px 22px rgba(2,6,23,0.04);
      display:flex; flex-direction:column; gap:8px;
    }
    .course-row{ display:flex; gap:12px; align-items:flex-start; }
    .course-thumb{
      width:56px; height:56px; border-radius:10px; background:linear-gradient(135deg, rgba(99,102,241,0.12), rgba(6,182,212,0.08));
      display:flex; align-items:center; justify-content:center; color:var(--accent); font-weight:700; font-size:18px;
      flex-shrink:0;
    }
    .course-meta h3{ margin:0; font-size:15px; font-weight:700; line-height:1.1; }
    .course-meta .teacher{ color:var(--muted); font-size:12px; margin-top:4px; }
    .desc { color:var(--muted); font-size:13px; margin-top:6px; max-height:2.6em; overflow:hidden; text-overflow:ellipsis; }

    .progress-wrap{ display:flex; align-items:center; gap:10px; margin-top:6px; }
    .progress { height:8px; border-radius:8px; width:100%; background:#eef2ff; overflow:hidden; }
    .progress > i{ display:block; height:100%; width:0%; background:linear-gradient(90deg,var(--accent), #06b6d4); transition:width .35s ease; }

    .card-actions{ display:flex; gap:8px; margin-top:8px; }
    .btn-primary-wide{ flex:1; border-radius:10px; padding:10px 12px; font-weight:700; }
    .btn-secondary-small{ border-radius:10px; padding:8px 10px; font-weight:600; }

    .empty { text-align:center; color:var(--muted); padding:18px; background:rgba(255,255,255,0.6); border-radius:10px; }

    /* bottom nav */
    nav.bottom-bar{
      position:fixed; left:50%; transform:translateX(-50%); bottom:12px; width:calc(100% - 24px); max-width:440px;
      background:rgba(255,255,255,0.98); padding:8px; border-radius:14px; box-shadow:0 10px 30px rgba(2,6,23,0.08);
      display:flex; gap:8px; justify-content:space-between; z-index:40;
    }
    .nav-item{ flex:1; text-align:center; font-size:12px; color:#0f172a; padding:8px 6px; border-radius:8px; }
    .nav-item .bi{ display:block; font-size:18px; margin-bottom:4px; color:var(--accent); }

    @media (max-height:640px) {
      main.container{ padding-bottom:100px; }
      .hero{ padding:10px; }
      .course-thumb{ width:48px; height:48px; }
    }
  </style>
</head>
<body>

  <header class="page-header" role="banner">
    <!-- Back button (left) -->
    <a href="/dashboard.php" class="btn-back" aria-label="Back to dashboard">
      <i class="bi bi-arrow-left-short" style="font-size:20px;"></i> Back
    </a>

    <div style="flex:1; text-align:center; pointer-events:none;">
      <div style="font-weight:800; font-size:16px; color:#fff;">My Courses</div>
      <div style="font-size:12px; opacity:.9; color:rgba(255,255,255,0.92); margin-top:2px;">Enrolled courses</div>
    </div>

    <!-- small profile dot (right) -->
    <a href="/profile.php" class="profile-icon" aria-label="Profile">
      <i class="bi bi-person-fill" style="font-size:18px;"></i>
    </a>
  </header>

  <main class="container" role="main">
    <div class="hero" aria-hidden="false">
      <div style="width:56px; height:56px; border-radius:10px; background:linear-gradient(135deg, rgba(99,102,241,0.12), rgba(6,182,212,0.08)); display:flex; align-items:center; justify-content:center; color:var(--accent); font-weight:800;">
        <i class="bi bi-journal" style="font-size:22px;"></i>
      </div>
      <div>
        <div class="greet">Welcome back</div>
        <div class="sub">Open a course to continue learning — fast, easy and mobile-first.</div>
      </div>
    </div>

    <?php if(empty($courses)): ?>
      <div class="empty">
        <p style="margin:0 0 8px;">You aren't enrolled in any courses yet.</p>
        <a href="/courses.php" class="btn btn-primary btn-lg w-100">Browse Courses</a>
      </div>
    <?php else: ?>

      <?php foreach($courses as $course):
        // compute progress for current user (0..100)
        $progress = 0;
        try {
          $progress = course_progress($conn, (int)$course['id'], $uid);
        } catch(Throwable $e) {
          $progress = 0;
        }
      ?>
        <article class="course-card" role="article" aria-labelledby="course-<?php echo (int)$course['id']; ?>">
          <div class="course-row">
            <div class="course-thumb" aria-hidden="true">
              <?php
                // initials
                $parts = preg_split('/\s+/', trim($course['title']));
                $initials = strtoupper(substr($parts[0],0,1) . (isset($parts[1])?substr($parts[1],0,1):''));
                echo htmlspecialchars($initials);
              ?>
            </div>

            <div style="flex:1;">
              <div class="course-meta">
                <h3 id="course-<?php echo (int)$course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></h3>
                <div class="teacher"><?php echo htmlspecialchars($course['teacher'] ?? '—'); ?></div>
              </div>

              <?php if(!empty($course['description'])): ?>
                <div class="desc"><?php echo htmlspecialchars($course['description']); ?></div>
              <?php endif; ?>

              <div class="progress-wrap" aria-hidden="false">
                <div style="flex:1;">
                  <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo $progress; ?>">
                    <i style="width: <?php echo $progress; ?>%;"></i>
                  </div>
                </div>
                <div style="min-width:46px; text-align:right; font-weight:700; color:var(--accent);">
                  <?php echo $progress; ?>%
                </div>
              </div>

              <div class="card-actions">
                <a href="/course_view.php?id=<?php echo (int)$course['id']; ?>" class="btn btn-primary btn-primary-wide">Open Course</a>
                <a href="/course_view.php?id=<?php echo (int)$course['id']; ?>#materials" class="btn btn-outline-secondary btn-secondary-small">Materials</a>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>

    <?php endif; ?>

  </main>

  <nav class="bottom-bar" role="navigation" aria-label="Primary">
    <a class="nav-item" href="/dashboard.php"><i class="bi bi-house"></i>Home</a>
    <a class="nav-item" href="/student/my_courses.php"><i class="bi bi-journal"></i>Courses</a>
    <a class="nav-item" href="/notifications.php"><i class="bi bi-bell"></i>Alerts</a>
    <a class="nav-item" href="/profile.php"><i class="bi bi-gear"></i>Profile</a>
  </nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
