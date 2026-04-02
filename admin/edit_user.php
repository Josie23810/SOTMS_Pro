<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
checkAccess(['admin']);

$user_id = intval($_GET['id'] ?? 0);
if (!$user_id) {
    header('Location: manage_users.php');
    exit();
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    $_SESSION['error'] = 'User not found.';
    header('Location: manage_users.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    try {
        $pdo->prepare('UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?')->execute([$name, $email, $role, $user_id]);
        $_SESSION['success'] = 'User updated successfully.';
        header('Location: manage_users.php');
        exit();
    } catch (PDOException $e) {
        $error = 'Error updating user: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(rgba(15,23,42,0.7), rgba(15,23,42,0.7)), url('../uploads/image005.jpg') center/cover fixed; }
        .container { max-width:600px; margin:50px auto; background:white; padding:40px; border-radius:20px; box-shadow:0 25px 50px rgba(0,0,0,0.2); }
        .form-group { margin-bottom:25px; }
        label { display:block; font-weight:600; margin-bottom:8px; color:#374151; }
        input, select { width:100%; padding:12px 16px; border:2px solid #e5e7eb; border-radius:12px; font-size:16px; transition: border-color 0.2s; }
        input:focus, select:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
        .btn { padding:14px 28px; border:none; border-radius:12px; font-size:16px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; margin:5px; transition: all 0.2s; }
        .btn-primary { background:linear-gradient(135deg, #3b82f6, #1d4ed8); color:white; }
        .btn-secondary { background:#6b7280; color:white; }
        .btn:hover { transform: translateY(-2px); box-shadow:0 10px 25px rgba(0,0,0,0.2); }
        .error { background:#fee2e2; color:#dc2626; padding:12px; border-radius:12px; margin-bottom:20px; border:1px solid #fecaca; }
    </style>
</head>
<body>
    <div class="container">
        <h1>✏️ Edit User: <?=htmlspecialchars($user['name'])?></h1>
        <?php if (isset($error)): ?>
            <div class="error"><?=htmlspecialchars($error)?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" value="<?=htmlspecialchars($user['name'])?>" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?=htmlspecialchars($user['email'])?>" required>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" required>
                    <option value="student" <?= $user['role']=='student' ? 'selected' : '' ?>>Student</option>
                    <option value="tutor" <?= $user['role']=='tutor' ? 'selected' : '' ?>>Tutor</option>
                    <option value="admin" <?= $user['role']=='admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Update User</button>
                <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>

