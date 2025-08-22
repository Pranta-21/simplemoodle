<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

// fetch current user
$uid = current_user_id();
$user_name = "User";
$user_email = "";
$role = $_SESSION['role'] ?? 'student';

$stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
if ($u) {
    $user_name = $u['name'] ?: $user_name;
    $user_email = $u['email'] ?? '';
}
$stmt->close();

// quick stats
$coursesCount = 0;
$assignmentsCount = 0;
$announcementsCount = 0;
$latestAnnouncement = null;

if (is_teacher()) {
    // number of courses teacher owns
    $s = $conn->prepare("SELECT COUNT(*) AS cnt FROM courses WHERE teacher_id = ?");
    $s->bind_param("i", $uid); $s->execute();
    $coursesCount = (int)$s->get_result()->fetch_assoc()['cnt']; $s->close();

    // assignments across teacher courses
    $s = $conn->prepare("SELECT COUNT(a.id) AS cnt FROM assignments a JOIN courses c ON a.course_id=c.id WHERE c.teacher_id = ?");
    $s->bind_param("i", $uid); $s->execute();
    $assignmentsCount = (int)$s->get_result()->fetch_assoc()['cnt']; $s->close();

    // announcements count & latest (teacher's courses)
    $s = $conn->prepare("SELECT COUNT(a.id) AS cnt FROM announcements a JOIN courses c ON a.course_id=c.id WHERE c.teacher_id = ?");
    $s->bind_param("i", $uid); $s->execute();
    $announcementsCount = (int)$s->get_result()->fetch_assoc()['cnt']; $s->close();

    $s = $conn->prepare("SELECT a.message, a.created_at, c.title AS course_title FROM announcements a JOIN courses c ON a.course_id=c.id WHERE c.teacher_id = ? ORDER BY a.id DESC LIMIT 1");
    $s->bind_param("i", $uid); $s->execute();
    $latestAnnouncement = $s->get_result()->fetch_assoc() ?: null;
    $s->close();

} else { // student (or other roles)
    // courses enrolled
    $s = $conn->prepare("SELECT COUNT(*) AS cnt FROM enrollments WHERE user_id = ?");
    $s->bind_param("i", $uid); $s->execute();
    $coursesCount = (int)$s->get_result()->fetch_assoc()['cnt']; $s->close();

    // assignments in enrolled courses (upcoming or all)
    $s = $conn->prepare("
        SELECT COUNT(a.id) AS cnt
        FROM assignments a
        JOIN enrollments e ON e.course_id = a.course_id
        WHERE e.user_id = ?");
    $s->bind_param("i", $uid); $s->execute();
    $assignmentsCount = (int)$s->get_result()->fetch_assoc()['cnt']; $s->close();

    // announcements for enrolled courses
    $s = $conn->prepare("
        SELECT COUNT(a.id) AS cnt
        FROM announcements a
        JOIN enrollments e ON e.course_id = a.course_id
        WHERE e.user_id = ?");
    $s->bind_param("i", $uid); $s->execute();
    $announcementsCount = (int)$s->get_result()->fetch_assoc()['cnt']; $s->close();

    $s = $conn->prepare("
        SELECT a.message, a.created_at, c.title AS course_title
        FROM announcements a
        JOIN enrollments e ON e.course_id = a.course_id
        JOIN courses c ON c.id = a.course_id
        WHERE e.user_id = ?
        ORDER BY a.id DESC LIMIT 1");
    $s->bind_param("i", $uid); $s->execute();
    $latestAnnouncement = $s->get_result()->fetch_assoc() ?: null;
    $s->close();
}

// safe helper for initials avatar
function initials($name) {
    $parts = preg_split("/\s+/", trim($name));
    if (count($parts) === 1) return strtoupper(substr($parts[0], 0, 1));
    return strtoupper(substr($parts[0],0,1) . substr(end($parts),0,1));
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard · MoodleClone</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    /* Mobile-first: target ~6" Android portrait (360–412px width) */
    :root {
      --accent-1: #6366f1;
      --accent-2: #06b6d4;
      --card-radius: 14px;
    }
    html,body { height:100%; }
    body {
      margin:0;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 100%);
      -webkit-font-smoothing:antialiased;
      color: #0f172a;
    }

    /* Remove underlines from links site-wide (including visited/hover/focus) */
    a, a:link, a:visited, a:hover, a:active, a:focus {
      text-decoration: none !important;
    }
    /* keep keyboard focus visible for accessibility */
    a:focus {
      outline: 3px solid rgba(99,102,241,0.18);
      outline-offset: 2px;
    }
    /* gentle hover cue so links still feel interactive */
    a:hover { opacity: .95; }

    /* header */
    header.app-header {
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:12px 14px;
      background: linear-gradient(90deg,var(--accent-1), var(--accent-2));
      color:#fff;
      box-shadow: 0 6px 20px rgba(15,23,42,0.08);
    }
    .header-left { display:flex; align-items:center; gap:12px; }
    .avatar {
      width:46px; height:46px; border-radius:12px;
      background: rgba(255,255,255,0.12);
      display:flex; align-items:center; justify-content:center;
      font-weight:700; font-size:16px; color:#fff;
    }
    .user-meta { line-height:1; }
    .user-meta .role { font-size:12px; opacity:0.9; }
    .logout-btn { color:#fff; border: 1px solid rgba(255,255,255,0.15); padding:6px 10px; border-radius:10px; font-weight:600; font-size:0.9rem; background:transparent; }

    /* container */
    main.container {
      max-width:440px;
      margin: 14px auto;
      padding: 0 12px;
      width:100%;
      flex:1;
    }

    /* welcome */
    .welcome {
      margin-top:8px;
      background: linear-gradient(180deg, rgba(255,255,255,0.9), #fff);
      border-radius: var(--card-radius);
      padding:14px;
      box-shadow: 0 6px 18px rgba(2,6,23,0.06);
    }
    .welcome h2 { margin:0; font-size:18px; }
    .welcome p { margin:4px 0 0; color:#475569; font-size:13px; }

    /* stats row */
    .stats-row { display:flex; gap:10px; margin-top:12px; }
    .stat {
      flex:1; background:#fff; border-radius:12px; padding:10px;
      box-shadow: 0 6px 16px rgba(2,6,23,0.04); text-align:center;
    }
    .stat .num { font-size:18px; font-weight:700; }
    .stat .lbl { font-size:12px; color:#6b7280; margin-top:4px; }

    /* action tiles */
    .tiles { margin-top:14px; display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
    .tile {
      background: linear-gradient(180deg, #ffffff, #fbfdff);
      border-radius:14px; padding:12px; display:flex; flex-direction:column; gap:8px;
      align-items:flex-start; box-shadow: 0 8px 24px rgba(2,6,23,0.04);
    }
    .tile .icon {
      width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center;
      background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(6,182,212,0.08));
      font-size:20px; color:var(--accent-1);
    }
    .tile h4 { margin:0; font-size:15px; }
    .tile p { margin:0; color:#475569; font-size:13px; }

    /* announcement */
    .announce {
      margin-top:14px; background:#fff; border-radius:12px; padding:12px;
      box-shadow: 0 6px 18px rgba(2,6,23,0.04);
    }
    .announce small { color:#6b7280; }
    .announce p { margin:6px 0 0; font-size:14px; color:#0f172a; }

    /* bottom nav */
    nav.bottom-nav {
      position:fixed; left:50%; transform:translateX(-50%);
      bottom:12px; width:calc(100% - 28px); max-width:440px;
      display:flex; gap:8px; justify-content:space-between;
      background: rgba(255,255,255,0.98); padding:8px; border-radius:14px;
      box-shadow: 0 10px 30px rgba(2,6,23,0.08);
    }
    .nav-btn { flex:1; text-align:center; font-size:13px; color:#0f172a; padding:10px; border-radius:10px; }
    .nav-btn .bi { display:block; font-size:18px; margin-bottom:4px; color:var(--accent-1); }

    /* small adjustments for very short windows */
    @media (max-height:650px) {
      .welcome { padding:10px; }
      .tile { padding:10px; }
      .stat { padding:8px; }
      nav.bottom-nav { padding:6px; bottom:8px; }
    }
  </style>
</head>
<body>
  <header class="app-header">
    <div class="header-left">
      <div class="avatar" aria-hidden="true"><?php echo htmlspecialchars(initials($user_name)); ?></div>
      <div class="user-meta">
        <div style="font-weight:700; font-size:15px;"><?php echo htmlspecialchars($user_name); ?></div>
        <div class="role"><?php echo htmlspecialchars(ucfirst($role)); ?></div>
      </div>
    </div>

    <div>
      <form method="post" action="logout.php" style="display:inline">
        <button class="logout-btn" type="submit" aria-label="Logout">
          <i class="bi bi-box-arrow-right" style="margin-right:6px;"></i> Logout
        </button>
      </form>
    </div>
  </header>

  <main class="container" role="main">
    <section class="welcome" aria-labelledby="welcomeTitle">
      <h2 id="welcomeTitle">Welcome, <?php echo htmlspecialchars($user_name); ?></h2>
      <p>You're signed in as <strong><?php echo htmlspecialchars(ucfirst($role)); ?></strong>. Start where you left off.</p>

      <div class="stats-row" aria-hidden="false">
        <div class="stat" title="Courses">
          <div class="num"><?php echo $coursesCount; ?></div>
          <div class="lbl">Courses</div>
        </div>
        <div class="stat" title="Assignments">
          <div class="num"><?php echo $assignmentsCount; ?></div>
          <div class="lbl">Assignments</div>
        </div>
      </div>
    </section>

    <section class="tiles" aria-label="Quick actions">
      <?php if (is_teacher()): ?>
        <a class="tile" href="/teacher/manage_courses.php" role="button">
          <div class="icon"><i class="bi bi-journal-bookmark"></i></div>
          <h4>Manage Courses</h4>
          <p>Create, edit or remove course content.</p>
        </a>
        <a class="tile" href="/teacher/announcements.php" role="button">
          <div class="icon"><i class="bi bi-megaphone"></i></div>
          <h4>Announcements</h4>
          <p>Post updates to students quickly.</p>
        </a>
      <?php elseif (is_student()): ?>
        <a class="tile" href="/student/my_courses.php" role="button">
          <div class="icon"><i class="bi bi-kanban"></i></div>
          <h4>My Courses</h4>
          <p>Open your enrolled courses.</p>
        </a>
        <a class="tile" href="/courses.php" role="button">
          <div class="icon"><i class="bi bi-search"></i></div>
          <h4>Browse Courses</h4>
          <p>Find and enroll in new courses.</p>
        </a>
      <?php else: ?>
        <a class="tile" href="/courses.php" role="button">
          <div class="icon"><i class="bi bi-grid"></i></div>
          <h4>Courses</h4>
          <p>Browse available courses.</p>
        </a>
        <a class="tile" href="/profile.php" role="button">
          <div class="icon"><i class="bi bi-person-lines-fill"></i></div>
          <h4>Profile</h4>
          <p>Manage your account settings.</p>
        </a>
      <?php endif; ?>
    </section>

    <?php if ($latestAnnouncement): ?>
      <section class="announce" aria-labelledby="announceTitle">
        <small id="announceTitle">Latest announcement</small>
        <p><strong><?php echo htmlspecialchars($latestAnnouncement['course_title'] ?? 'Course'); ?>:</strong>
          <?php echo htmlspecialchars(mb_strimwidth($latestAnnouncement['message'], 0, 220, '…')); ?></p>
        
      </section>
    <?php endif; ?>

  </main>

  <nav class="bottom-nav" role="navigation" aria-label="Primary">
    
    <a class="nav-btn" href="/courses.php"><i class="bi bi-journal"></i> Courses</a>

    <a class="nav-btn" href="/alerts.php"><i class="bi bi-bell"></i>Alerts</a>
    <a class="nav-btn" href="/profile.php"><i class="bi bi-gear"></i>Profile</a>
  </nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
