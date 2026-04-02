<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
checkAccess(['admin']);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        try {
            $pdo->beginTransaction();
            
            // Delete sessions first
            $pdo->prepare('DELETE FROM sessions WHERE student_id IN (SELECT id FROM students WHERE user_id = ?) OR tutor_id IN (SELECT id FROM tutors WHERE user_id = ?)')->execute([$user_id, $user_id]);
            
            // Delete profiles
            $pdo->prepare('DELETE FROM students WHERE user_id = ?')->execute([$user_id]);
            $pdo->prepare('DELETE FROM tutors WHERE user_id = ?')->execute([$user_id]);
            
            // Finally delete user
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id]);
            
            $pdo->commit();
            $message = 'User and all related data deleted successfully.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'Error deleting user: ' . $e->getMessage();
        }
    }

}

// Fetch all users
$stmt = $pdo->query('SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC');
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(rgba(15,23,42,0.7), rgba(15,23,42,0.7)), url('../uploads/image005.jpg') center/cover fixed; margin:0; padding:20px; }
        .container { max-width:1400px; margin:0 auto; background:rgba(255,255,255,0.95); border-radius:20px; overflow:hidden; box-shadow:0 25px 50px rgba(0,0,0,0.2); }
        .header { background:linear-gradient(135deg, #ef4444, #dc2626); color:white; padding:25px; text-align:center; }
        .header h1 { margin:0; font-size:2.2rem; }
        .nav-top { background:#f8fafc; padding:15px 25px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; }
        .nav-top a { color:#2563eb; text-decoration:none; font-weight:600; padding:10px 20px; border-radius:8px; background:#dbeafe; }
        .content { padding:30px; }
        .message { padding:15px; border-radius:12px; margin-bottom:25px; font-weight:600; }
        .message.success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
        .message.error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        table { width:100%; border-collapse:collapse; margin-top:20px; background:white; border-radius:12px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.1); }
        th { background:linear-gradient(135deg, #2563eb, #3b82f6); color:white; padding:18px 15px; text-align:left; font-weight:600; }
        td { padding:15px; border-bottom:1px solid #f1f5f9; }
        tr:hover { background:#f8fafc; }
        .action-btn { padding:8px 12px; border-radius:6px; font-size:0.85rem; font-weight:600; margin:0 3px; text-decoration:none; }
        .btn-edit { background:#10b981; color:white; }
        .btn-delete { background:#ef4444; color:white; }
        .role-badge { padding:4px 10px; border-radius:20px; font-size:0.8rem; font-weight:600; text-transform:uppercase; }
        .role-student { background:#dbeafe; color:#1d4ed8; }
        .role-tutor { background:#fef3c7; color:#b45309; }
        .role-admin { background:#fee2e2; color:#991b1b; }
        #usersTable { width:100%; }
        @media (max-width:768px) { .nav-top { flex-direction:column; gap:15px; } table { font-size:0.9rem; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 Manage Users</h1>
            <p>Complete user management with search, edit & delete capabilities</p>
        </div>
        
        <div class="nav-top">
            <div>
                <h3>Total Users: <?php echo count($users); ?></h3>
            </div>
            <div>
                <a href="dashboard.php" class="nav-top a">← Back to Dashboard</a>
            </div>
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="role-badge role-<?php echo strtolower($user['role']); ?>"><?php echo ucfirst($user['role']); ?></span></td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="action-btn btn-edit">Edit</a>
                                <?php if ($user['role'] !== 'admin'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete <?php echo htmlspecialchars($user['name']); ?>?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="delete_user" value="1">
                                        <button type="submit" class="action-btn btn-delete">Delete</button>
                                    </form>
                                <?php endif; ?>
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
                responsive: true,
                dom: 'Bfrtip',
                buttons: ['copy', 'csv', 'excel', 'pdf']
            });
        });
    </script>
</body>
</html>
