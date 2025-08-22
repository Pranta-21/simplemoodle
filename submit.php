<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_role(['student']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: courses.php"); exit(); }

$assignment_id = (int)($_POST['assignment_id'] ?? 0);
if ($assignment_id <= 0 || !isset($_FILES['file'])) { die("Invalid request."); }

$student_id = current_user_id();

// basic file validation
$allowed = ['pdf','doc','docx','zip','txt','ppt','pptx','xls','xlsx'];
$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed, true)) {
    die("Invalid file type.");
}
if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    die("Upload error.");
}

@mkdir(__DIR__ . '/uploads', 0775, true);
$basename = 'a'.$assignment_id.'_u'.$student_id.'_'.time().'.'.$ext;
$target = __DIR__ . '/uploads/' . $basename;
$webPath = 'uploads/' . $basename;

if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
    die("Failed to move uploaded file.");
}

// upsert submission (one per student per assignment)
$chk = $conn->prepare("SELECT id FROM submissions WHERE assignment_id=? AND student_id=?");
$chk->bind_param("ii", $assignment_id, $student_id);
$chk->execute();
$exist = $chk->get_result()->fetch_assoc();

if ($exist) {
    $upd = $conn->prepare("UPDATE submissions SET file_path=?, grade=NULL WHERE id=?");
    $upd->bind_param("si", $webPath, $exist['id']);
    $upd->execute();
} else {
    $ins = $conn->prepare("INSERT INTO submissions (assignment_id, student_id, file_path) VALUES (?,?,?)");
    $ins->bind_param("iis", $assignment_id, $student_id, $webPath);
    $ins->execute();
}

header("Location: assignment.php?id=".$assignment_id);
exit();
