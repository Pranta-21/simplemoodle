<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_role(['teacher']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: courses.php"); exit(); }

$submission_id = (int)($_POST['submission_id'] ?? 0);
$grade = trim($_POST['grade'] ?? '');

if ($submission_id <= 0 || $grade === '') { die("Invalid request."); }

// ensure the submission belongs to an assignment of this teacher
$q = $conn->prepare("SELECT s.id, a.id as assignment_id, c.teacher_id 
                     FROM submissions s 
                     JOIN assignments a ON a.id=s.assignment_id
                     JOIN courses c ON c.id=a.course_id
                     WHERE s.id=?");
$q->bind_param("i", $submission_id);
$q->execute();
$found = $q->get_result()->fetch_assoc();
if (!$found || (int)$found['teacher_id'] !== (int)current_user_id()) {
    http_response_code(403);
    die("Not allowed.");
}

$u = $conn->prepare("UPDATE submissions SET grade=? WHERE id=?");
$u->bind_param("si", $grade, $submission_id);
$u->execute();

header("Location: assignment.php?id=".(int)$found['assignment_id']);
exit();
