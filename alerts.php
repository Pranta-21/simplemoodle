<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

/**
 * Only students may view Alerts.
 * If your auth.php provides require_role, this enforces it.
 * The fallback check below covers older installs.
 */
if (function_exists('require_role')) {
    require_role(['student']);
} else {
    require_login();
    if (!is_student()) { header("HTTP/1.1 403 Forbidden"); exit("Forbidden"); }
}

$user_id = current_user_id();

/** Fetch announcements for courses the student is enrolled in */
$sql = "
  SELECT a.id, a.message, a.created_at,
         c.id AS course_id, c.title AS course_title,
         u.name AS teacher_name
  FROM announcements a
  JOIN courses c      ON c.id = a.course_id
  JOIN users   u      ON u.id = a.teacher_id
  JOIN enrollments e  ON e.course_id = a.course_id
  WHERE e.user_id = ?
  ORDER BY a.created_at DESC, a.id DESC
  LIMIT 100
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$announcements = $stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Alerts · MoodleClone</title>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body{background:#f6f8fb;}
  .wrap{max-width:460px;margin:0 auto;padding:16px 14px 90px;}
  .appbar{
    position:sticky;top:0;z-index:10;
    display:flex;align-items:center;gap:10px;
    padding:12px 14px;background:linear-gradient(90deg,#3b82f6,#06b6d4);
    color:#fff;box-shadow:0 6px 24px rgba(59,130,246,.12);
  }
  .back-pill{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.12);
    color:#fff;padding:8px 10px;border-radius:10px;border:0;font-weight:600;font-size:14px;}
  .title{font-weight:700;font-size:16px;margin:0;flex:1;text-align:center;}

  .alert-card{
    background:#fff;border-radius:14px;padding:14px 16px;margin:12px 0;
    box-shadow:0 10px 28px rgba(15,23,42,.06);
  }
  .course{font-weight:700;color:#0f172a;font-size:15px;margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .msg{color:#111827;font-size:14px;line-height:1.45;white-space:pre-wrap;}
  .meta{font-size:12px;color:#6b7280;margin-top:8px;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;}
  .view-btn{border-radius:10px;padding:6px 10px;}
  .bottom-nav{
    position:fixed;left:50%;transform:translateX(-50%);bottom:16px;z-index:70;
    width:min(760px,calc(100% - 28px));max-width:460px;background:#fff;border-radius:16px;padding:12px;
    box-shadow:0 18px 40px rgba(15,23,42,.08);display:flex;justify-content:space-around;align-items:center;
  }
  .nav-item{display:flex;flex-direction:column;align-items:center;gap:6px;font-size:13px;color:#6b7280;text-decoration:none}
</style>
</head>
<body>

<header class="appbar">
  <button class="back-pill" onclick="history.back()" aria-label="Back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Back
  </button>
  <h1 class="title">Announcements</h1>
  <div style="width:40px"></div>
</header>

<div class="wrap">
  <?php if ($announcements && $announcements->num_rows > 0): ?>
    <?php while ($row = $announcements->fetch_assoc()): ?>
      <div class="alert-card">
        <div class="course"><?php echo htmlspecialchars($row['course_title']); ?></div>
        <div class="msg"><?php echo nl2br(htmlspecialchars($row['message'])); ?></div>
        <div class="meta">
          <span>By <?php echo htmlspecialchars($row['teacher_name']); ?></span>
          <span><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></span>
          <a class="btn btn-sm btn-outline-primary view-btn" href="course_view.php?id=<?php echo (int)$row['course_id']; ?>">View course</a>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <div class="alert alert-secondary">No announcements yet.</div>
  <?php endif; ?>
</div>

<nav class="bottom-nav" role="navigation" aria-label="Bottom Navigation">
  <a href="index.php" class="nav-item"><span>🏠</span><span>Home</span></a>
  <a href="courses.php" class="nav-item"><span>📘</span><span>Courses</span></a>
  <a href="alerts.php" class="nav-item"><span>🔔</span><span>Alerts</span></a>
  <a href="profile.php" class="nav-item"><span>👤</span><span>Profile</span></a>
</nav>

</body>
</html>
