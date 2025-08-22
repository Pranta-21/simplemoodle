<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($assignment_id <= 0) { header("Location: courses.php"); exit(); }

// fetch assignment + course + teacher
$stmt = $conn->prepare("SELECT a.*, c.title AS course_title, c.teacher_id FROM assignments a 
                        JOIN courses c ON c.id=a.course_id WHERE a.id=?");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$as = $stmt->get_result()->fetch_assoc();
if (!$as) { echo "Assignment not found."; exit(); }

function nl2br_esc($s){ return nl2br(htmlspecialchars($s)); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?php echo htmlspecialchars($as['title']); ?> — Assignment · MoodleClone</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root{
    --primary1:#3b82f6; /* blue */
    --primary2:#06b6d4; /* teal */
    --muted:#6b7280;
    --card:#ffffff;
  }
  html,body{height:100%;}
  body{
    margin:0;
    font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial;
    background: linear-gradient(180deg,#f8fafc 0%, #eef2ff 100%);
    -webkit-font-smoothing:antialiased;
    display:flex;
    flex-direction:column;
    min-height:100vh;
    color:#0f172a;
  }

  /* App header */
  header.appbar{
    display:flex; align-items:center; gap:8px;
    padding:12px 14px;
    background: linear-gradient(90deg,var(--primary1),var(--primary2));
    color:#fff;
    box-shadow:0 6px 18px rgba(59,130,246,0.12);
    position:sticky; top:0; z-index:60;
  }
  .back-pill{
    display:inline-flex; align-items:center; gap:8px;
    background:rgba(255,255,255,0.12); padding:8px 10px; border-radius:10px;
    color:#fff; border:0; font-weight:700; font-size:14px;
  }
  .title-wrap{ flex:1; text-align:center; font-weight:700; font-size:16px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .profile-btn{ width:40px; height:40px; border-radius:10px; background:rgba(255,255,255,0.12); display:inline-flex; align-items:center; justify-content:center; }

  /* Main viewport */
  .viewport{ max-width:420px; width:100%; margin:14px auto; padding:0 14px 110px; }

  /* Card */
  .card-app{ background:var(--card); border-radius:14px; padding:14px; box-shadow:0 14px 36px rgba(15,23,42,0.04); margin-bottom:12px; }

  /* Assignment header area */
  .assignment-head{ display:flex; gap:12px; align-items:flex-start; }
  .assign-badge{ width:56px; height:56px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:linear-gradient(180deg,#fff,#f3f4ff); color:var(--primary1); font-weight:800; font-size:18px; flex-shrink:0; }
  .assign-meta h2{ margin:0; font-size:18px; font-weight:800; }
  .assign-meta .course{ color:var(--muted); font-size:13px; margin-top:6px; }
  .assign-meta .deadline{ margin-top:8px; color:#7c3aed; font-weight:700; font-size:13px; }

  /* Description */
  .description{ margin-top:10px; color:var(--muted); font-size:14px; line-height:1.4; white-space:pre-wrap; }

  /* Student submit card */
  .submit-card{ display:flex; flex-direction:column; gap:10px; }
  .file-input{ border-radius:10px; padding:10px; background:#f8fafc; border:1px dashed #e6eefc; }
  .btn-submit{ border-radius:12px; padding:12px; font-weight:800; background:linear-gradient(90deg,var(--primary1),var(--primary2)); color:#fff; border:0; box-shadow:0 10px 30px rgba(59,130,246,0.12); }

  /* Submission list (student or teacher) */
  .submission-list{ display:flex; flex-direction:column; gap:10px; margin-top:6px; }
  .submission-item{ background:#fff; border-radius:12px; padding:12px; display:flex; gap:12px; align-items:center; box-shadow:0 10px 28px rgba(15,23,42,0.04); }
  .sub-avatar{ width:46px; height:46px; border-radius:10px; background:linear-gradient(180deg,#f8fafc,#eef2ff); display:flex; align-items:center; justify-content:center; color:var(--primary1); font-weight:800; flex-shrink:0; }
  .sub-meta{ flex:1; min-width:0; }
  .sub-meta .name{ font-weight:700; font-size:14px; margin-bottom:4px; }
  .sub-meta .meta{ color:var(--muted); font-size:13px; }

  /* Teacher grading row */
  .grade-row{ display:flex; gap:8px; align-items:center; margin-top:8px; }
  .grade-row input[type="text"]{ flex:1; padding:10px 12px; border-radius:10px; border:1px solid #e6e9f2; }
  .grade-row button{ padding:10px 12px; border-radius:10px; background:#0ea5a4; color:#fff; border:0; font-weight:700; }

  /* Footer nav */
  .bottom-nav{ position:fixed; left:50%; transform:translateX(-50%); bottom:16px; z-index:70;
    width: min(760px, calc(100% - 28px)); max-width:420px; background:#fff; border-radius:16px; padding:12px;
    box-shadow:0 18px 40px rgba(15,23,42,0.08); display:flex; justify-content:space-around; align-items:center; }

  .nav-item{ display:flex; flex-direction:column; align-items:center; gap:6px; font-size:13px; color:var(--muted); text-decoration:none; }
  .nav-item svg{ width:20px; height:20px; }

  /* small messages */
  .toast{ padding:10px 12px; border-radius:10px; margin-bottom:10px; font-weight:700; }
  .toast.ok{ background:#ecfdf5; color:#065f46; border:1px solid #bbf7d0; }
  .toast.err{ background:#fff3f2; color:#9f1239; border:1px solid #fecdd3; }

  @media (max-width:420px){
    .viewport{ padding-left:12px; padding-right:12px; }
    .assign-badge{ width:52px; height:52px; }
    .sub-avatar{ width:44px; height:44px; }
    .grade-row input[type="text"]{ padding:10px; }
  }
</style>
</head>
<body>

<header class="appbar">
  <button class="back-pill" onclick="history.back()" aria-label="Back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="margin-top:1px"><path d="M15 18l-6-6 6-6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Back
  </button>

  <div class="title-wrap"><?php echo htmlspecialchars($as['title']); ?></div>

  <a href="profile.php" class="profile-btn" aria-label="Profile">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zM4 20c0-2.21 3.58-4 8-4s8 1.79 8 4" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </a>
</header>

<main class="viewport">

  <!-- Assignment summary card -->
  <section class="card-app">
    <div class="assignment-head">
      <div class="assign-badge">A</div>
      <div class="assign-meta">
        <h2><?php echo htmlspecialchars($as['title']); ?></h2>
        <div class="course">Course: <?php echo htmlspecialchars($as['course_title']); ?></div>
        <div class="deadline">Deadline: <?php echo htmlspecialchars($as['deadline']); ?></div>
      </div>
    </div>

    <div class="description"><?php echo nl2br_esc($as['description']); ?></div>
  </section>

  <?php if (is_student()): ?>
    <!-- Student submit area -->
    <section class="card-app">
      <h3 style="margin-top:0; margin-bottom:8px; font-size:16px;">Submit Your Work</h3>
      <form action="submit.php" method="post" enctype="multipart/form-data" class="submit-card">
        <input type="hidden" name="assignment_id" value="<?php echo (int)$assignment_id; ?>">
        <label class="file-input">
          <input type="file" name="file" required style="width:100%; border:0; background:transparent;">
        </label>
        <button type="submit" class="btn-submit">Upload Submission</button>
      </form>

      <div style="margin-top:12px;">
        <h4 style="margin-bottom:8px;">Your Submission</h4>
        <div class="submission-list">
          <?php
            $sid = current_user_id();
            $q = $conn->prepare("SELECT * FROM submissions WHERE assignment_id=? AND student_id=? ORDER BY id DESC");
            $q->bind_param("ii", $assignment_id, $sid);
            $q->execute();
            $sub = $q->get_result()->fetch_assoc();
            if ($sub):
          ?>
            <div class="submission-item">
              <div class="sub-avatar">S</div>
              <div class="sub-meta">
                <div class="name">You</div>
                <div class="meta"><?php echo htmlspecialchars($sub['file_path']); ?></div>
                <div class="meta" style="margin-top:6px;">Grade: <strong><?php echo htmlspecialchars($sub['grade'] ?? 'Pending'); ?></strong></div>
              </div>
              <div style="display:flex; gap:8px; align-items:center;">
                <a href="<?php echo htmlspecialchars($sub['file_path']); ?>" class="btn btn-outline-primary" style="border-radius:10px; padding:8px 10px;" target="_blank">Download</a>
              </div>
            </div>
          <?php else: ?>
            <div style="color:var(--muted);">No submission yet.</div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if (is_teacher() && (int)$as['teacher_id'] === (int)current_user_id()): ?>
    <!-- Teacher: list student submissions -->
    <section class="card-app">
      <h3 style="margin-top:0; margin-bottom:8px; font-size:16px;">Student Submissions</h3>

      <div class="submission-list">
        <?php
        $s = $conn->prepare("SELECT s.*, u.name FROM submissions s 
                             JOIN users u ON u.id=s.student_id
                             WHERE s.assignment_id=? ORDER BY s.id DESC");
        $s->bind_param("i", $assignment_id);
        $s->execute();
        $rs = $s->get_result();
        if ($rs->num_rows === 0):
        ?>
          <div style="color:var(--muted);">No submissions yet.</div>
        <?php
        else:
          while($row = $rs->fetch_assoc()):
        ?>
          <div class="submission-item">
            <div class="sub-avatar"><?php echo htmlspecialchars(mb_substr($row['name'],0,1)); ?></div>
            <div class="sub-meta">
              <div class="name"><?php echo htmlspecialchars($row['name']); ?></div>
              <div class="meta"><?php echo htmlspecialchars($row['file_path']); ?></div>
              <div class="meta" style="margin-top:6px;">Current grade: <strong><?php echo htmlspecialchars($row['grade'] ?? 'Pending'); ?></strong></div>

              <form method="post" action="grade_submission.php" class="grade-row" style="margin-top:8px;">
                <input type="hidden" name="submission_id" value="<?php echo (int)$row['id']; ?>">
                <input type="text" name="grade" placeholder="e.g. A+, A, B" required value="<?php echo htmlspecialchars($row['grade'] ?? ''); ?>">
                <button type="submit" class="btn-grade">Update</button>
              </form>
            </div>

            <div style="display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
              <a href="<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank" class="btn btn-outline-primary" style="border-radius:10px; padding:8px 10px;">Download</a>
            </div>
          </div>
        <?php
          endwhile;
        endif;
        ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!empty($_GET['msg'])): ?>
    <div class="toast <?php echo ($_GET['msg']=='ok' ? 'ok' : 'err'); ?>"><?php echo htmlspecialchars($_GET['text'] ?? ''); ?></div>
  <?php endif; ?>

</main>

<nav class="bottom-nav" role="navigation" aria-label="Bottom Navigation">
  <a href="index.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none"><path d="M3 11.5L12 5l9 6.5" stroke="#6b7280" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Home</span></a>
  <a href="courses.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none"><path d="M3 7h18v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z" stroke="#6b7280" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 3v4M8 3v4" stroke="#6b7280" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Courses</span></a>
  <a href="alerts.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none"><path d="M15 17H9" stroke="#6b7280" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 2a5 5 0 0 0-5 5v3H5l1 5h12l1-5h-2V7a5 5 0 0 0-5-5z" stroke="#6b7280" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Alerts</span></a>
  <a href="profile.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 20a8 8 0 0 1 16 0" stroke="#6b7280" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Profile</span></a>
</nav>

</body>
</html>
