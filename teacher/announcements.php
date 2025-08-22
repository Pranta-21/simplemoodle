<?php
// teacher/announcements.php (mobile-first, Android ~6")
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['teacher']);

// current teacher id
$current_teacher_id = (int) current_user_id();

// Accept course_id from GET or POST so the POST knows the course
$course_id = (int)($_REQUEST['course_id'] ?? 0);

// Fetch teacher's courses for the selector
$courses = [];
$cc = $conn->prepare("SELECT id, title FROM courses WHERE teacher_id = ? ORDER BY id DESC");
$cc->bind_param("i", $current_teacher_id);
$cc->execute();
$cres = $cc->get_result();
while ($cr = $cres->fetch_assoc()) $courses[] = $cr;
$cc->close();

// If a course_id is provided, verify it exists (it may belong to another teacher)
$course_title = '';
$course_teacher_id = null;
if ($course_id > 0) {
    $cchk = $conn->prepare("SELECT title, teacher_id FROM courses WHERE id = ?");
    $cchk->bind_param("i", $course_id);
    $cchk->execute();
    $cres2 = $cchk->get_result()->fetch_assoc();
    $cchk->close();
    if ($cres2) {
        $course_title = $cres2['title'];
        $course_teacher_id = (int)$cres2['teacher_id'];
    } else {
        // invalid course id - clear it so page shows selector
        $course_id = 0;
    }
}

$alert = "";

/* =========================================
   DELETE HANDLER (runs before create)
   ========================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)($_POST['delete_id'] ?? 0);

    // Verify ownership of announcement
    $chk = $conn->prepare("SELECT teacher_id, course_id FROM announcements WHERE id=?");
    $chk->bind_param("i", $delete_id);
    $chk->execute();
    $info = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($info && (int)$info['teacher_id'] === $current_teacher_id) {
        $del = $conn->prepare("DELETE FROM announcements WHERE id=?");
        $del->bind_param("i", $delete_id);
        $del->execute();
        $del->close();

        header("Location: announcements.php?course_id=".(int)$info['course_id']."&deleted=1");
        exit();
    } else {
        $alert = "<div class='alert alert-danger small'>Not allowed to delete this announcement.</div>";
    }
}

/* =========================================
   CREATE HANDLER
   ========================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])) {
    // Ensure course_id is read from POST (the form sets this)
    $course_id = (int)($_POST['course_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if ($course_id <= 0) {
        $alert = "<div class='alert alert-danger small'>Please choose or enter a valid course id.</div>";
    } elseif ($message === '') {
        $alert = "<div class='alert alert-danger small'>Please enter an announcement message.</div>";
    } else {
        $teacher_id = $current_teacher_id;

        $stmt = $conn->prepare("INSERT INTO announcements (course_id, teacher_id, message, created_at) VALUES (?,?,?,NOW())");
        if ($stmt === false) {
            $alert = "<div class='alert alert-danger small'>Prepare failed: " . htmlspecialchars($conn->error) . "</div>";
        } else {
            $stmt->bind_param("iis", $course_id, $teacher_id, $message);
            if ($stmt->execute()) {
                $stmt->close();

                // create notifications for enrolled users (optional)
                $notif_sql = "
                  INSERT INTO notifications (user_id, course_id, type, message, url, is_read, created_at)
                  SELECT e.user_id, ?, 'announcement', ?, CONCAT('/course_view.php?id=', ?), 0, NOW()
                  FROM enrollments e
                  WHERE e.course_id = ?
                ";
                if ($nstmt = $conn->prepare($notif_sql)) {
                    $nstmt->bind_param("isii", $course_id, $message, $course_id, $course_id);
                    $nstmt->execute();
                    $nstmt->close();
                }

                header("Location: announcements.php?course_id=" . (int)$course_id . "&posted=1");
                exit();
            } else {
                $alert = "<div class='alert alert-danger small'>Failed to post announcement: " . htmlspecialchars($stmt->error) . "</div>";
                $stmt->close();
            }
        }
    }
}

// After redirect, show success alerts
if (isset($_GET['posted']) && (int)$_GET['posted'] === 1) {
    $alert = "<div class='alert alert-success small'>Announcement posted successfully.</div>";
}
if (isset($_GET['deleted']) && (int)$_GET['deleted'] === 1) {
    $alert = "<div class='alert alert-success small'>Announcement deleted.</div>";
}

// Fetch announcements for the selected course (if any)
$announcements = [];
if ($course_id > 0) {
    $q = $conn->prepare("SELECT a.*, u.name FROM announcements a JOIN users u ON u.id=a.teacher_id WHERE a.course_id=? ORDER BY a.id DESC");
    $q->bind_param("i", $course_id);
    $q->execute();
    $r = $q->get_result();
    while ($row = $r->fetch_assoc()) $announcements[] = $row;
    $q->close();
}

// helper: initials for small avatar
function initials($str) {
    $s = trim($str);
    if (!$s) return 'T';
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
  <title>Announcements · MoodleClone</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --brand:#6366f1;
      --accent:#06b6d4;
      --muted:#6b7280;
      --card-radius:12px;
      --maxw:440px;
    }
    html,body{height:100%;}
    body{
      margin:0; font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial;
      background: linear-gradient(180deg,#f7fbff,#eef2ff);
      color:#071033; -webkit-font-smoothing:antialiased;
    }

    /* --- REMOVE ALL UNDERLINES, KEEP ACCESSIBLE FOCUS --- */
    a, a:hover, a:focus, a:active, a:visited { text-decoration: none !important; text-decoration-skip-ink: auto; }
    a:focus-visible, button:focus-visible, .btn:focus-visible {
      outline: 2px solid rgba(99,102,241,.65);
      outline-offset: 2px;
      box-shadow: 0 0 0 4px rgba(99,102,241,.15);
    }
    .nav-link, .link-primary, .link-secondary, .link-dark, .btn, .btn-back, .nav-item { text-decoration: none !important; }

    /* Top appbar */
    .appbar {
      display:flex; align-items:center; justify-content:space-between; gap:10px;
      padding:12px; background:linear-gradient(90deg,var(--brand),var(--accent)); color:#fff;
      box-shadow:0 10px 30px rgba(2,6,23,0.08); position:sticky; top:0; z-index:50;
    }
    .btn-back {
      display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,0.12);
      color:#fff; border:0; padding:8px 10px; border-radius:10px; font-weight:700;
    }
    .app-title { text-align:center; flex:1; pointer-events:none; }
    .app-title h1 { margin:0; font-size:16px; font-weight:800; }
    .app-title small { display:block; opacity:.95; font-size:12px; }

    main.container { max-width:var(--maxw); margin:12px auto; padding:0 12px 120px; width:100%; }

    .card { border-radius:var(--card-radius); background:#fff; padding:12px; box-shadow:0 10px 30px rgba(2,6,23,0.06); margin-bottom:12px; }
    .selector-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .selector-row .form-select, .selector-row .form-control { border-radius:10px; }

    .compose-area textarea { resize:none; min-height:84px; border-radius:10px; padding:12px; font-size:14px; }
    .char-counter { font-size:.85rem; color:var(--muted); }

    .announcement { border-radius:10px; padding:12px; margin-bottom:10px; background:linear-gradient(180deg,#fff,#fbfdff); border:1px solid #eef6ff; box-shadow:0 8px 20px rgba(2,6,23,0.04); }
    .announcement .meta { display:flex; justify-content:space-between; align-items:center; gap:12px; font-size:.9rem; color:#374151; }
    .announcement .message { margin-top:8px; color:#0f172a; white-space:pre-wrap; font-size:14px; }

    .avatar { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:800; color:#fff; background:linear-gradient(90deg,var(--brand),var(--accent)); font-size:16px; }

    .btn-primary, .btn-outline-secondary, .btn-outline-danger { border-radius:10px; font-weight:700; }
    .action-right { display:flex; gap:6px; align-items:center; min-width:fit-content; }

    .small-muted { color:var(--muted); font-size:.9rem; }

    /* bottom nav */
    nav.bottom-nav {
      position:fixed; left:50%; transform:translateX(-50%); bottom:12px; width:calc(100% - 28px); max-width:var(--maxw);
      padding:8px; display:flex; gap:8px; justify-content:space-between; background:rgba(255,255,255,0.98);
      border-radius:14px; box-shadow:0 12px 36px rgba(2,6,23,0.08); z-index:50;
    }
    .nav-item { flex:1; text-align:center; font-size:12px; color:#071033; padding:8px 6px; border-radius:8px; }
    .nav-item .bi { display:block; font-size:18px; margin-bottom:4px; color:var(--brand); }

    @media (max-width:360px) {
      .avatar { width:40px; height:40px; font-size:14px; }
      .compose-area textarea { min-height:72px; }
    }
  </style>
</head>
<body>

<header class="appbar" role="banner">
  <button class="btn-back" onclick="history.back()" aria-label="Back">
    <i class="bi bi-arrow-left-short" style="font-size:18px;"></i> Back
  </button>

  <div class="app-title" aria-hidden="true">
    <h1>Course Announcements</h1>
    <small>Post notices to students — select a course first</small>
  </div>

  <a href="/teacher/manage_courses.php" class="btn-back" style="padding:8px 10px;">My Courses</a>
</header>

<main class="container" role="main" aria-live="polite">

  <?php if (!empty($alert)) echo $alert; ?>

  <!-- course selector -->
  <div class="card" role="region" aria-label="Course selector">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <strong>Select Course</strong>
      <span class="small-muted">Choose or enter ID</span>
    </div>

    <div class="selector-row mb-2">
      <div style="flex:1; min-width:150px;">
        <label class="small-muted mb-1">Your courses</label>
        <select id="courseSelect" class="form-select form-select-sm">
          <option value="0">-- Choose a course --</option>
          <?php foreach ($courses as $c): ?>
            <option value="<?php echo (int)$c['id']; ?>" <?php if ($course_id === (int)$c['id']) echo 'selected'; ?>>
              <?php echo htmlspecialchars($c['title'] . " (ID: " . $c['id'] . ")"); ?>
            </option>
          <?php endforeach; ?>
          <option value="-1" <?php if ($course_id > 0 && !in_array($course_id, array_column($courses,'id'))) echo 'selected'; ?>>Other (enter id)</option>
        </select>
      </div>

      <div style="width:140px;">
        <label class="small-muted mb-1">Or Course ID</label>
        <input id="courseManual" type="number" min="1" class="form-control form-control-sm" placeholder="Course id" value="<?php echo ($course_id>0 && !in_array($course_id, array_column($courses,'id')))?(int)$course_id:''; ?>">
      </div>

      <div style="display:flex; align-items:end;">
        <button id="loadBtn" class="btn btn-primary btn-sm">Load</button>
      </div>
    </div>

    <div class="small-muted">Selecting a course will load its announcements. Enter a numeric ID if the course isn’t in your list.</div>
  </div>

  <!-- compose -->
  <div class="card" role="region" aria-label="Post announcement">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <strong>Post Announcement</strong>
      <span class="small-muted">Notifications will be sent to enrolled students</span>
    </div>

    <form id="announceForm" method="post" class="compose-area" autocomplete="off">
      <input type="hidden" name="course_id" id="courseHidden" value="<?php echo (int)$course_id; ?>">
      <div class="mb-2">
        <textarea id="messageInput" name="message" class="form-control" maxlength="2000" placeholder="Write your announcement here..." <?php if ($course_id<=0) echo 'disabled'; ?> required></textarea>
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <div class="char-counter"><span id="charsLeft">2000</span> characters left</div>
        <div class="d-flex gap-2">
          <button type="button" id="previewBtn" class="btn btn-outline-secondary btn-sm" <?php if ($course_id<=0) echo 'disabled'; ?>>Preview</button>
          <button type="submit" class="btn btn-primary btn-sm" <?php if ($course_id<=0) echo 'disabled'; ?>>Post</button>
        </div>
      </div>
    </form>

    <?php if ($course_id<=0): ?>
      <div class="mt-2 small-muted">Select or enter a course id first to enable posting.</div>
    <?php endif; ?>
  </div>

  <!-- announcements list -->
  <div role="region" aria-label="Announcements list">
    <div style="display:flex; justify-content:space-between; align-items:center; margin:6px 0;">
      <h6 class="small-muted mb-0"><?php echo $course_id>0 ? "Announcements for: " . htmlspecialchars($course_title) : "Announcements (select a course)"; ?></h6>
      <?php if ($course_id>0): ?>
        <a class="small-muted" href="/course_view.php?id=<?php echo (int)$course_id; ?>">View course page</a>
      <?php endif; ?>
    </div>

    <?php if ($course_id <= 0): ?>
      <div class="alert alert-secondary small">No course selected. Use the selector above to load announcements for a specific course.</div>
    <?php else: ?>
      <?php if (empty($announcements)): ?>
        <div class="alert alert-secondary small">No announcements yet for this course.</div>
      <?php else: ?>
        <?php foreach ($announcements as $row): ?>
          <div class="announcement" role="article" aria-labelledby="ann-<?php echo (int)$row['id']; ?>">
            <div class="meta">
              <div style="display:flex; gap:10px; align-items:center;">
                <div class="avatar" aria-hidden="true"><?php echo htmlspecialchars(initials($row['name'])); ?></div>
                <div>
                  <div style="font-weight:800;"><?php echo htmlspecialchars($row['name']); ?></div>
                  <div class="small-muted" style="font-size:12px;"><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></div>
                </div>
              </div>

              <div class="action-right">
                <a href="/course_view.php?id=<?php echo (int)$row['course_id']; ?>" class="btn btn-outline-secondary btn-sm">Open</a>

                <?php if ((int)$row['teacher_id'] === $current_teacher_id): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this announcement? This cannot be undone.');">
                    <input type="hidden" name="delete_id" value="<?php echo (int)$row['id']; ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                      <i class="bi bi-trash3"></i> Delete
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>

            <div id="ann-<?php echo (int)$row['id']; ?>" class="message"><?php echo nl2br(htmlspecialchars($row['message'])); ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</main>

<!-- preview modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Announcement preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body"><div id="previewContent" style="white-space:pre-wrap;"></div></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button id="postFromPreview" type="button" class="btn btn-primary btn-sm">Post</button>
      </div>
    </div>
  </div>
</div>

<nav class="bottom-nav" aria-label="Primary navigation">
  <a class="nav-item" href="/dashboard.php"><i class="bi bi-house"></i><div>Home</div></a>
  <a class="nav-item" href="/teacher/manage_courses.php"><i class="bi bi-journal"></i> Manage</a>
  <a class="nav-item" href="/profile.php"><i class="bi bi-person"></i> Profile</a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const courseSelect = document.getElementById('courseSelect');
  const courseManual = document.getElementById('courseManual');
  const loadBtn = document.getElementById('loadBtn');
  const courseHidden = document.getElementById('courseHidden');
  const messageInput = document.getElementById('messageInput');
  const charsLeft = document.getElementById('charsLeft');
  const maxLen = messageInput ? messageInput.getAttribute('maxlength') : 2000;
  const previewModalElem = document.getElementById('previewModal');
  const previewModal = new bootstrap.Modal(previewModalElem);
  const previewContent = document.getElementById('previewContent');

  // autosize textarea + char counter
  function updateTA(){
    if(!messageInput) return;
    messageInput.style.height = 'auto';
    messageInput.style.height = (messageInput.scrollHeight) + 'px';
    const left = maxLen - (messageInput.value.length || 0);
    charsLeft.textContent = left;
  }
  if (messageInput) {
    messageInput.addEventListener('input', updateTA);
    window.addEventListener('load', updateTA);
  }

  // Load button: navigate to announcements.php?course_id=...
  loadBtn.addEventListener('click', function(){
    let cid = parseInt(courseSelect.value, 10);
    if (cid === -1) { // "Other (enter id)"
      cid = parseInt(courseManual.value || 0, 10);
      if (!cid || cid <= 0) {
        alert('Please enter a valid course id.');
        return;
      }
    } else if (!cid || cid <= 0) {
      alert('Please choose a course from the list or enter an ID.');
      return;
    }
    window.location.href = 'announcements.php?course_id=' + cid;
  });

  // manual id typing selects Other
  courseManual.addEventListener('input', function(){
    if (this.value && parseInt(this.value,10)>0) courseSelect.value = -1;
  });

  // Preview button
  document.getElementById('previewBtn').addEventListener('click', function(){
    const val = messageInput.value.trim();
    if (!val) { alert('Please enter a message to preview.'); return; }
    const esc = val.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;');
    previewContent.innerHTML = esc.replace(/\n/g, '<br>');
    previewModal.show();
  });

  // Post from preview: submit form
  document.getElementById('postFromPreview').addEventListener('click', function(){
    const cid = parseInt(new URLSearchParams(window.location.search).get('course_id') || 0, 10);
    if (!cid) { alert('Course id missing. Load a course first.'); previewModal.hide(); return; }
    document.getElementById('announceForm').submit();
  });
</script>
</body>
</html>
