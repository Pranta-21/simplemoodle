<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($course_id <= 0) { header("Location: courses.php"); exit(); }

// course info
$stmt = $conn->prepare("SELECT c.*, u.name AS teacher_name FROM courses c LEFT JOIN users u ON u.id=c.teacher_id WHERE c.id=?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
if (!$course) { echo "Course not found."; exit(); }

$enrolled = false;
if (is_student()) {
    $chk = $conn->prepare("SELECT id FROM enrollments WHERE user_id=? AND course_id=?");
    $uid = current_user_id();
    $chk->bind_param("ii", $uid, $course_id);
    $chk->execute();
    $enrolled = (bool)$chk->get_result()->fetch_assoc();
}

$msg = "";
if (is_teacher() && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_assignment'])) {
    $title = trim($_POST['title']);
    $desc  = trim($_POST['description']);
    $deadline = $_POST['deadline'];
    if ($title !== '' && $deadline !== '') {
        $ins = $conn->prepare("INSERT INTO assignments (course_id, title, description, deadline) VALUES (?,?,?,?)");
        $ins->bind_param("isss", $course_id, $title, $desc, $deadline);
        $ins->execute();
        $msg = "<div class='toast-success'>Assignment created.</div>";
    } else {
        $msg = "<div class='toast-error'>Title and deadline are required.</div>";
    }
}

$material_msg = "";
if (is_teacher() && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['upload_material'])) {
    if(isset($_FILES['file']) && $_FILES['file']['error']===0){
        $title = trim($_POST['title']);
        $file_name = basename($_FILES['file']['name']);
        $target_dir = __DIR__ . "/uploads/materials/";
        if(!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        $target_file = $target_dir . time() . "_" . $file_name;
        if(move_uploaded_file($_FILES['file']['tmp_name'], $target_file)){
            $ins = $conn->prepare("INSERT INTO course_materials (course_id, title, file_path) VALUES (?,?,?)");
            $rel_path = "uploads/materials/" . time() . "_" . $file_name;
            $ins->bind_param("iss", $course_id, $title, $rel_path);
            $ins->execute();
            $material_msg = "<div class='toast-success'>Material uploaded.</div>";
        } else {
            $material_msg = "<div class='toast-error'>Failed to upload file.</div>";
        }
    } else {
        $material_msg = "<div class='toast-error'>Please select a file.</div>";
    }
}

function initials($name){
    $parts = preg_split("/\s+/", trim($name));
    $letters = "";
    foreach($parts as $p){ if($p!=="") $letters .= mb_substr($p,0,1); }
    return mb_strtoupper(mb_substr($letters,0,2));
}

function human_filesize($file){
    if(!file_exists($file)) return '';
    $sz = filesize($file);
    if($sz >= 1048576) return round($sz/1048576,2).' MB';
    if($sz >= 1024) return round($sz/1024,1).' KB';
    return $sz.' B';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?php echo htmlspecialchars($course['title']); ?> · MoodleClone</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root{
    --primary1:#3b82f6;
    --primary2:#06b6d4;
    --muted:#6b7280;
    --card:#ffffff;
  }
  html,body{height:100%;}
  body{
    margin:0; font-family:Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial;
    background: linear-gradient(180deg,#f8fafc 0%, #eef2ff 100%);
    -webkit-font-smoothing:antialiased;
    display:flex; flex-direction:column; min-height:100vh;
  }

  header.appbar{
    display:flex; align-items:center; justify-content:space-between;
    padding:12px 14px; background:linear-gradient(90deg,var(--primary1),var(--primary2));
    color:#fff; box-shadow:0 6px 24px rgba(59,130,246,0.12); position:sticky; top:0; z-index:60;
  }
  .back-pill{
    display:flex; align-items:center; gap:8px; background:rgba(255,255,255,0.12);
    color:#fff; padding:8px 10px; border-radius:10px; font-weight:600; font-size:14px; border:0;
  }
  .app-title{ font-weight:700; font-size:16px; text-align:center; flex:1; margin:0 8px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .avatar-btn{ width:40px; height:40px; border-radius:10px; background:rgba(255,255,255,0.12); display:flex; align-items:center; justify-content:center; }

  .viewport{ max-width:420px; width:100%; margin:14px auto; padding:0 14px 120px; }

  /* card styles */
  .item-card{
    background:var(--card);
    border-radius:14px;
    padding:18px;
    margin-bottom:8px;
    box-shadow:0 14px 36px rgba(15,23,42,0.04);
    display:flex;
    gap:14px;
    align-items:flex-start;
    min-height:88px;
  }

  .file-badge{
    width:64px;
    height:64px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(180deg,#f8fafc,#f2f5ff);
    color:var(--primary1);
    font-weight:800;
    font-size:16px;
    flex-shrink:0;
  }

  .item-meta{ flex:1; min-width:0; display:flex; flex-direction:column; justify-content:center; }
  .item-meta .title{ font-weight:700; color:#0f172a; margin-bottom:6px; font-size:15px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .item-meta .sub{ color:var(--muted); font-size:13px; }

  /* footer button under each card */
  .item-footer{
    margin:8px 0 14px;
    display:flex;
    justify-content:flex-end;
  }
  .item-footer .btn{
    width:100%;
    border-radius:12px;
    padding:10px 12px;
    font-weight:700;
  }
  .btn-outline-primary.full{ background:#fff; color:#374151; border:1px solid #e6e9f2; }

  /* upload / assignment forms */
  .upload-form .form-control{ height:44px; border-radius:10px; }
  .upload-form textarea.form-control{ min-height:88px; border-radius:10px; }
  .assignment-form .form-control{ height:44px; border-radius:10px; }
  .assignment-form textarea.form-control{ min-height:88px; border-radius:10px; }

  .btn{ border-radius:12px; }

  /* bottom nav */
  .bottom-nav{
    position:fixed; left:50%; transform:translateX(-50%); bottom:16px; z-index:70;
    width: min(760px, calc(100% - 28px)); max-width:420px; background: #fff; border-radius:16px; padding:12px;
    box-shadow:0 18px 40px rgba(15,23,42,0.08); display:flex; justify-content:space-around; align-items:center;
  }
  .nav-item{ display:flex; flex-direction:column; align-items:center; gap:6px; font-size:13px; color:var(--muted); text-decoration:none; }
  .nav-item svg{ width:22px; height:22px; }

  .toast-success,.toast-error{ padding:10px 12px; border-radius:10px; margin-bottom:10px; font-weight:600; }
  .toast-success{ background:#ecfdf5; color:#065f46; border:1px solid #bbf7d0; }
  .toast-error{ background:#fff3f2; color:#9f1239; border:1px solid #fecdd3; }

  @media (max-width:420px){
    .item-card{ padding:16px; min-height:84px; }
    .file-badge{ width:56px; height:56px; }
    .upload-form .form-control, .assignment-form .form-control{ height:42px; }
    .upload-form textarea.form-control, .assignment-form textarea.form-control{ min-height:76px; }
  }
</style>
</head>
<body>

<header class="appbar">
  <button class="back-pill" onclick="history.back()" aria-label="Back">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="margin-top:1px"><path d="M15 18l-6-6 6-6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Back
  </button>

  <div class="app-title"><?php echo htmlspecialchars($course['title']); ?></div>

  <a href="profile.php" class="avatar-btn" aria-label="Profile">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zM4 20c0-2.21 3.58-4 8-4s8 1.79 8 4" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
  </a>
</header>

<div class="viewport">

  <div style="display:flex; justify-content:space-between; align-items:center; margin:8px 0 6px;">
    <strong style="font-size:15px">Materials</strong>
    <?php if(is_teacher() && (int)$course['teacher_id']===current_user_id()): ?>
      <span style="color:var(--muted); font-size:13px">Upload allowed</span>
    <?php else: ?>
      <span style="color:var(--muted); font-size:13px"><?php echo $enrolled ? 'Enrolled' : 'Not enrolled'; ?></span>
    <?php endif; ?>
  </div>

  <?php if(is_teacher() && (int)$course['teacher_id']===current_user_id()): ?>
  <div class="item-card upload-form" style="flex-direction:column; align-items:stretch;">
    <form method="post" enctype="multipart/form-data" class="row g-2">
      <input type="hidden" name="upload_material" value="1">
      <div class="col-12"><input type="text" name="title" class="form-control form-control-sm" placeholder="Material Title" required></div>
      <div class="col-12"><input type="file" name="file" class="form-control form-control-sm" required></div>
      <div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-success w-100">Upload</button></div>
    </form>
  </div>
  <?php endif; ?>

  <?php
  $m = $conn->prepare("SELECT * FROM course_materials WHERE course_id=? ORDER BY id DESC");
  $m->bind_param("i",$course_id);
  $m->execute();
  $res_m = $m->get_result();
  if($res_m->num_rows==0) {
    echo "<div style='color:var(--muted); margin-bottom:12px;'>No materials uploaded yet.</div>";
  }
  while($mat = $res_m->fetch_assoc()):
    $fullpath = __DIR__ . '/' . $mat['file_path'];
    $ext = pathinfo($mat['file_path'], PATHINFO_EXTENSION);
    $sizeStr = human_filesize($fullpath);
  ?>
    <div class="item-card" id="materials">
      <div class="file-badge"><?php echo htmlspecialchars(strtoupper($ext ?: 'F')); ?></div>
      <div class="item-meta">
        <div class="title"><?php echo htmlspecialchars($mat['title']); ?></div>
        <div class="sub"><?php echo htmlspecialchars($mat['file_path']); ?> <?php if($sizeStr) echo ' • '.$sizeStr; ?></div>
      </div>
    </div>
    <div class="item-footer">
      <a href="<?php echo htmlspecialchars($mat['file_path']); ?>" target="_blank" class="btn btn-outline-primary full">Open / Download</a>
    </div>
  <?php endwhile; ?>

  <div style="display:flex; justify-content:space-between; align-items:center; margin:12px 0 6px;">
    
  </div>

  <?php
  $a = $conn->prepare("SELECT * FROM assignments WHERE course_id=? ORDER BY id DESC");
  $a->bind_param("i", $course_id);
  $a->execute();
  $res = $a->get_result();
  if($res->num_rows==0) {
    echo "<div style='color:var(--muted); margin-bottom:12px;'>No assignments yet.</div>";
  }
  while($as = $res->fetch_assoc()):
    $dl = htmlspecialchars($as['deadline']);
  ?>
    <div class="item-card">
      <div style="width:64px;height:64px;border-radius:12px;background:linear-gradient(180deg,#fff,#f5f8ff);display:flex;align-items:center;justify-content:center;color:var(--primary1);font-weight:800;font-size:18px;">
        A
      </div>
      <div class="item-meta">
        <div class="title"><?php echo htmlspecialchars($as['title']); ?></div>
        <div class="sub">Deadline: <?php echo $dl; ?></div>
      </div>
    </div>
    <div class="item-footer">
      <a href="assignment.php?id=<?php echo (int)$as['id']; ?>" class="btn btn-outline-primary full">View Assignment</a>
    </div>
  <?php endwhile; ?>

  <?php if(is_teacher() && (int)$course['teacher_id']===current_user_id()): ?>
    <div style="height:8px"></div>
    <div class="item-card assignment-form" style="flex-direction:column; align-items:stretch;">
      <form method="post" class="row g-2">
        <input type="hidden" name="create_assignment" value="1">
        <div class="col-12"><input name="title" class="form-control form-control-sm" placeholder="Assignment Title" required></div>
        <div class="col-12"><input name="deadline" type="date" class="form-control form-control-sm" required></div>
        <div class="col-12"><textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Assignment description"></textarea></div>
        <div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-success w-100">Create Assignment</button></div>
      </form>
    </div>
  <?php endif; ?>

  <?php if($msg) echo $msg; ?>
  <?php if($material_msg) echo $material_msg; ?>

</div>

<nav class="bottom-nav" role="navigation" aria-label="Bottom Navigation">
  <a href="index.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none"><path d="M3 11.5L12 5l9 6.5" stroke="#6b7280" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Home</span></a>
  <a href="courses.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none"><path d="M3 7h18v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z" stroke="#6b7280" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 3v4M8 3v4" stroke="#6b7280" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Courses</span></a>
  <a href="alerts.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none"><path d="M15 17H9" stroke="#6b7280" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 2a5 5 0 0 0-5 5v3H5l1 5h12l1-5h-2V7a5 5 0 0 0-5-5z" stroke="#6b7280" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Alerts</span></a>
  <a href="profile.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 20a8 8 0 0 1 16 0" stroke="#6b7280" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Profile</span></a>
</nav>

<script>
  (function(){
    var percent = 0;
    var elFill = document.getElementById('progressFill');
    var elPercent = document.getElementById('progressPercent');
    if(elFill) elFill.style.width = percent + '%';
    if(elPercent) elPercent.textContent = percent + '%';
  })();
</script>

</body>
</html>
