<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$msg = "";
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['user_id'], $_POST['role'])) {
    $uid = (int)$_POST['user_id'];
    $role = $_POST['role'];
    if (in_array($role, ['admin','teacher','student'], true)) {
        $u = $conn->prepare("UPDATE users SET role=? WHERE id=?");
        $u->bind_param("si", $role, $uid);
        $u->execute();
        $msg = "<p class='badge'>Role updated.</p>";
    }
}

include __DIR__ . '/../includes/header.php';
?>
<h2>Manage Users</h2>
<?php echo $msg; ?>
<table>
  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Change Role</th></tr></thead>
  <tbody>
<?php
$r = $conn->query("SELECT id, name, email, role FROM users ORDER BY id DESC");
while($u = $r->fetch_assoc()):
?>
<tr>
  <td><?php echo htmlspecialchars($u['name']); ?></td>
  <td><?php echo htmlspecialchars($u['email']); ?></td>
  <td><span class="badge"><?php echo htmlspecialchars($u['role']); ?></span></td>
  <td>
    <form method="post" class="row" style="grid-template-columns:1fr auto;">
      <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
      <select name="role">
        <option value="student" <?php echo $u['role']==='student'?'selected':''; ?>>student</option>
        <option value="teacher" <?php echo $u['role']==='teacher'?'selected':''; ?>>teacher</option>
        <option value="admin"   <?php echo $u['role']==='admin'  ?'selected':''; ?>>admin</option>
      </select>
      <button type="submit">Save</button>
    </form>
  </td>
</tr>
<?php endwhile; ?>
  </tbody>
</table>
<?php include __DIR__ . '/../includes/footer.php'; ?>
