<?php
// courses.php (mobile-first, Android ~6" optimized)
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$uid = current_user_id();
$msg = "";

// Handle enroll POST (PRG pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_course_id']) && is_student()) {
    $course_id = (int) $_POST['enroll_course_id'];
    $uid = current_user_id();

    // check if already enrolled
    $chk = $conn->prepare("SELECT id FROM enrollments WHERE user_id=? AND course_id=? LIMIT 1");
    $chk->bind_param("ii", $uid, $course_id);
    $chk->execute();
    $already = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$already) {
        $stmt = $conn->prepare("INSERT INTO enrollments (user_id, course_id) VALUES (?,?)");
        $stmt->bind_param("ii", $uid, $course_id);
        if ($stmt->execute()) {
            $stmt->close();
            // redirect with success
            header("Location: courses.php?enrolled=1&cid={$course_id}");
            exit();
        } else {
            $stmt->close();
            header("Location: courses.php?enrolled=0&cid={$course_id}");
            exit();
        }
    } else {
        // already enrolled
        header("Location: courses.php?enrolled=exists&cid={$course_id}");
        exit();
    }
}

// Show messages after PRG
if (isset($_GET['enrolled'])) {
    switch ($_GET['enrolled']) {
        case '1':
            $msg = "<div class='alert alert-success small'>Enrolled successfully.</div>";
            break;
        case 'exists':
            $msg = "<div class='alert alert-info small'>You are already enrolled in this course.</div>";
            break;
        default:
            $msg = "<div class='alert alert-danger small'>Failed to enroll. Please try again.</div>";
            break;
    }
}

// fetch courses
$q = $conn->query("SELECT c.id, c.title, c.description, u.name AS teacher 
                   FROM courses c LEFT JOIN users u ON u.id=c.teacher_id 
                   ORDER BY c.id DESC");
$courses = $q ? $q->fetch_all(MYSQLI_ASSOC) : [];

function initials($s) {
    $s = trim($s);
    if ($s === '') return 'U';
    $parts = preg_split('/\s+/', $s);
    if (count($parts) === 1) return strtoupper(substr($parts[0],0,1));
    return strtoupper(substr($parts[0],0,1) . substr(end($parts),0,1));
}
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title>All Courses · MoodleClone</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --brand:#4f46e5;
      --accent:#06b6d4;
      --muted:#6b7280;
      --card-radius:12px;
      --max-width:440px;
    }
    html,body{height:100%;}
    body{
      margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial;
      background:linear-gradient(180deg,#f7fbff,#eef6ff); -webkit-font-smoothing:antialiased;
      color:#071033;
    }

    /* Header */
    header.app-header{
      display:flex; align-items:center; justify-content:space-between; gap:10px;
      padding:12px; background:linear-gradient(90deg,var(--brand),var(--accent)); color:#fff;
      box-shadow:0 10px 30px rgba(2,6,23,0.08); position:sticky; top:0; z-index:40;
    }
    .btn-back {
      display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,0.12); color:#fff;
      border:0; padding:8px 10px; border-radius:10px; font-weight:700;
      text-decoration:none; /* defensive */
    }
    .header-center { flex:1; text-align:center; pointer-events:none; }
    .header-center h1 { margin:0; font-size:16px; font-weight:800; }
    .header-center p { margin:0; font-size:12px; opacity:.95; }

    /* Container */
    main.container { max-width:var(--max-width); width:100%; margin:10px auto; padding:0 12px 110px; }

    /* Card */
    .course-card {
      background:linear-gradient(180deg,#fff,#fbfdff); border-radius:var(--card-radius); padding:12px; margin-bottom:12px;
      box-shadow:0 10px 30px rgba(2,6,23,0.06);
    }
    .course-top { display:flex; gap:12px; align-items:center; }
    .avatar {
      width:56px; height:56px; border-radius:12px; display:flex; align-items:center; justify-content:center;
      font-weight:800; color:#fff; background:linear-gradient(90deg,var(--brand),var(--accent)); font-size:18px;
    }
    .course-title { font-weight:800; margin:0; font-size:1rem; }
    .course-desc { color:var(--muted); font-size:13px; margin:6px 0 0; line-height:1.35; height:3.6em; overflow:hidden; }

    .teacher { color:var(--muted); font-size:12px; margin-top:8px; }

    .btn-big { width:100%; border-radius:10px; padding:10px; font-weight:800; }
    .btn-ghost { border-radius:10px; padding:8px 10px; font-weight:700; }

    /* bottom nav */
    nav.bottom-nav{
      position:fixed; left:50%; transform:translateX(-50%); bottom:12px; width:calc(100% - 28px); max-width:var(--max-width);
      padding:8px; display:flex; gap:8px; justify-content:space-between; background:rgba(255,255,255,0.98);
      border-radius:14px; box-shadow:0 12px 36px rgba(2,6,23,0.08); z-index:50;
    }
    .nav-item{ flex:1; text-align:center; font-size:12px; color:#071033; padding:8px 6px; border-radius:8px; }
    .nav-item .bi{ display:block; font-size:18px; margin-bottom:4px; color:var(--brand); }

    /* Remove underline from every word / link */
    /* This makes sure links don't show underlines on any state. */
    * {
      text-decoration: none !important;
    }
    /* Keep keyboard focus visible for accessibility */
    a:focus, button:focus, input:focus {
      outline: 3px solid rgba(79,70,229,0.18);
      outline-offset: 2px;
    }

    /* responsive adjustments */
    @media (max-width:360px) {
      .avatar { width:48px; height:48px; font-size:16px; }
      .course-desc { font-size:12px; height:3.2em; }
      .btn-big { padding:8px; }
      .header-center h1 { font-size:15px; }
    }
  </style>
</head>
<body>

<header class="app-header" role="banner">
  <a href="dashboard.php" class="btn-back" aria-label="Back to dashboard">
    <i class="bi bi-arrow-left-short" style="font-size:20px;"></i> Back
  </a>

  <div class="header-center" aria-hidden="true">
    <h1>All Courses</h1>
    <p>Your learning library</p>
  </div>

  <div style="display:flex; gap:8px; align-items:center;">
    <a href="profile.php" class="btn-back" style="padding:8px;"><i class="bi bi-person-circle" style="font-size:18px;"></i></a>
    <form method="post" action="logout.php" style="display:inline">
      <button class="btn-back" style="padding:8px;"><i class="bi bi-box-arrow-right" style="font-size:16px;"></i></button>
    </form>
  </div>
</header>

<main class="container" role="main" aria-live="polite">
  <?php if ($msg) echo $msg; ?>

  <?php if (empty($courses)): ?>
    <div class="card course-card text-center">
      <div>
        <p class="mb-2" style="font-weight:800;">No courses available</p>
        <p class="small-muted">Your organization hasn't created courses yet.</p>
      </div>
      <a href="dashboard.php" class="btn btn-primary btn-big mt-3">Back to dashboard</a>
    </div>
  <?php else: ?>
    <?php foreach ($courses as $course): ?>
      <article class="course-card" role="article" aria-labelledby="course-<?php echo (int)$course['id']; ?>">
        <div class="course-top">
          <div class="avatar" aria-hidden="true">
            <?php echo htmlspecialchars(initials($course['teacher'] ?? $course['title'])); ?>
          </div>
          <div style="flex:1;">
            <h3 id="course-<?php echo (int)$course['id']; ?>" class="course-title text-truncate"><?php echo htmlspecialchars($course['title']); ?></h3>
            <div class="teacher"><?php echo htmlspecialchars($course['teacher'] ?? '—'); ?></div>
            <p class="course-desc"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
          </div>
        </div>

        <div style="margin-top:10px;">
          <?php if (is_student()): ?>
            <form method="post" class="mb-0" style="display:block">
              <input type="hidden" name="enroll_course_id" value="<?php echo (int)$course['id']; ?>">
              <button type="submit" class="btn btn-primary btn-big" aria-label="Enroll in <?php echo htmlspecialchars($course['title']); ?>">Enroll</button>
            </form>
          <?php else: ?>
            <a href="course_view.php?id=<?php echo (int)$course['id']; ?>" class="btn btn-outline-primary btn-big">View Course</a>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>

  <div style="height:16px"></div>
  <a href="dashboard.php" class="btn btn-outline-dark w-100 mt-2">Back to Dashboard</a>
</main>

<nav class="bottom-nav" aria-label="Primary navigation">
  <a class="nav-item" href="/dashboard.php"><i class="bi bi-house"></i>Home</a>
  <a class="nav-item" href="/courses.php"><i class="bi bi-grid"></i>All</a>
  <a class="nav-item" href="/student/my_courses.php"><i class="bi bi-journal"></i>My</a>
  <a class="nav-item" href="/profile.php"><i class="bi bi-person"></i>Profile</a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // small entry animation for cards
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.course-card').forEach((el, i) => {
      el.style.opacity = 0;
      el.style.transform = 'translateY(8px)';
      setTimeout(()=> {
        el.style.transition = 'all 400ms cubic-bezier(.2,.9,.2,1)';
        el.style.opacity = 1;
        el.style.transform = 'translateY(0)';
      }, 80 * i);
    });
  });
</script>
</body>
</html>
