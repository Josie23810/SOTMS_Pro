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
    <link rel="stylesheet" href="../assets/css/admin_portal.css">
</head>
<body class="admin-portal">
    <div class="admin-form-card">
        <h1 style="margin-top:0;">Edit User</h1>
        <p style="color:#64748b; margin-top:-6px;"><?php echo htmlspecialchars($user['name']); ?></p>
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" required>
                    <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="tutor" <?php echo $user['role'] === 'tutor' ? 'selected' : ''; ?>>Tutor</option>
                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            <div class="stack-actions">
                <button type="submit" class="btn btn-primary">Update User</button>
                <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
