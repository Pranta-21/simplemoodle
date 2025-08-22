<?php
// Simple helpers for auth & role checks
if (session_status() === PHP_SESSION_NONE) session_start();

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /index.php");
        exit();
    }
}

function require_role($roles = []) {
    require_login();
    if (!in_array($_SESSION['role'] ?? 'student', $roles, true)) {
        http_response_code(403);
        echo "<h2>403 Forbidden</h2><p>You don't have permission to access this page.</p>";
        exit();
    }
}

function current_user_id() { return $_SESSION['user_id'] ?? null; }
function current_role() { return $_SESSION['role'] ?? 'guest'; }
function is_admin() { return current_role() === 'admin'; }
function is_teacher() { return current_role() === 'teacher'; }
function is_student() { return current_role() === 'student'; }
