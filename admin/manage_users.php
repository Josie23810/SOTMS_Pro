<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['admin']);
ensurePlatformStructures($pdo);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM sessions WHERE student_id IN (SELECT id FROM students WHERE user_id = ?) OR tutor_id IN (SELECT id FROM tutors WHERE user_id = ?)')->execute([$user_id, $user_id]);
            $pdo->prepare('DELETE FROM students WHERE user_id = ?')->execute([$user_id]);
            $pdo->prepare('DELETE FROM tutors WHERE user_id = ?')->execute([$user_id]);
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id]);
            $pdo->commit();
            $message = 'User and related data deleted successfully.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'Error deleting user: ' . $e->getMessage();
        }
    }
}

$stmt = $pdo->query("
    SELECT u.id, u.name, u.email, u.role, u.created_at, tp.verification_status
    FROM users u
    LEFT JOIN tutor_profiles tp ON tp.user_id = u.id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin_portal.css">
    <link href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" rel="stylesheet">
</head>
<body class="admin-portal">
    <div class="container">
        <div class="header">
            <h1>Manage Users</h1>
            <p>Review accounts, roles, and tutor verification status.</p>
        </div>

        <div class="nav-top">
            <h3>Total Users: <?php echo count($users); ?></h3>
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="tutor_verifications.php">Tutor Verifications</a>
            <a href="reports.php?export=users">Export CSV</a>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <table id="usersTable" class="display">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Registered</th>
                        <th>Verification</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo (int) $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="role-badge role-<?php echo strtolower($user['role']); ?>"><?php echo ucfirst($user['role']); ?></span></td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($user['role'] === 'tutor' ? str_replace('_', ' ', ($user['verification_status'] ?? 'submitted')) : 'N/A'); ?></td>
                            <td>
                                <div class="stack-actions">
                                    <a href="edit_user.php?id=<?php echo (int) $user['id']; ?>" class="action-btn btn-edit">Edit</a>
                                    <?php if ($user['role'] === 'tutor'): ?>
                                        <a href="tutor_verifications.php" class="action-btn">Review Tutor</a>
                                    <?php endif; ?>
                                    <?php if ($user['role'] !== 'admin'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete <?php echo htmlspecialchars($user['name']); ?>?');">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                            <input type="hidden" name="delete_user" value="1">
                                            <button type="submit" class="action-btn btn-delete">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#usersTable').DataTable({
                pageLength: 25,
                responsive: true
            });
        });
    </script>
</body>
</html>
