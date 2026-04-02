<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
checkAccess(['admin']);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_session'])) {
        $session_id = intval($_POST['session_id']);
        try {
            $pdo->prepare('DELETE FROM sessions WHERE id = ?')->execute([$session_id]);
            $message = 'Session deleted.';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_status'])) {
        $session_id = intval($_POST['session_id']);
        $status = $_POST['status'];
        try {
            $pdo->prepare('UPDATE sessions SET status = ? WHERE id = ?')->execute([$status, $session_id]);
            $message = 'Status updated.';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    }
}

// Fetch all sessions with joins
$stmt = $pdo->query('
    SELECT s.*, u_s.name as student_name, u_t.name as tutor_name
    FROM sessions s 
    LEFT JOIN users u_s ON s.student_id = u_s.id
    LEFT JOIN tutors t ON s.tutor_id = t.id
    LEFT JOIN users u_t ON t.user_id = u_t.id
    ORDER BY s.session_date DESC
');
$sessions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sessions - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(rgba(15,23,42,0.7), rgba(15,23,42,0.7)), url('../uploads/image005.jpg') center/cover fixed; margin:0; padding:20px; }
        .container { max-width:1400px; margin:0 auto; background:rgba(255,255,255,0.95); border-radius:20px; overflow:hidden; box-shadow:0 25px 50px rgba(0,0,0,0.2); }
        .header { background:linear-gradient(135deg, #8b5cf6, #7c3aed); color:white; padding:25px; text-align:center; }
        .header h1 { margin:0; font-size:2.2rem; }
        .nav-top { background:#f8fafc; padding:15px 25px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; }
        .nav-top a { color:#2563eb; text-decoration:none; font-weight:600; padding:10px 20px; border-radius:8px; background:#dbeafe; }
        .content { padding:30px; }
        .message { padding:15px; border-radius:12px; margin-bottom:25px; font-weight:600; }
        .success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
        .error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        table { width:100%; border-collapse:collapse; margin-top:20px; background:white; border-radius:12px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.1); }
        th { background:linear-gradient(135deg, #8b5cf6, #7c3aed); color:white; padding:18px 15px; text-align:left; font-weight:600; }
        td { padding:15px; border-bottom:1px solid #f1f5f9; }
        tr:hover { background:#f8fafc; }
        .status-select { padding:5px 8px; border-radius:6px; border:1px solid #d1d5db; background:white; }
        .action-btn { padding:8px 12px; border-radius:6px; font-size:0.85rem; font-weight:600; margin:0 3px; text-decoration:none; }
        .btn-edit { background:#10b981; color:white; }
        .btn-delete { background:#ef4444; color:white; }
        #sessionsTable { width:100%; }
        @media (max-width:768px) { table { font-size:0.9rem; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📅 Manage Sessions</h1>
            <p>All sessions across the platform - bulk status updates & management</p>
        </div>
        
        <div class="nav-top">
            <h3>Total Sessions: <?php echo count($sessions); ?></h3>
            <div>
                <a href="dashboard.php" class="nav-top a">← Dashboard</a>
                <a href="reports.php?type=sessions" class="nav-top a">📥 Export</a>
            </div>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo strpos($message, 'updated') !== false || strpos($message, 'deleted') !== false ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <table id="sessionsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Tutor</th>
                        <th>Subject</th>
                        <th>Date/Time</th>
                        <th>Status</th>
                        <th>Link</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td><?php echo $session['id']; ?></td>
                            <td><?php echo htmlspecialchars($session['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($session['tutor_name']); ?></td>
                            <td><?php echo htmlspecialchars($session['subject']); ?></td>
                            <td><?php echo date('M j g:i A', strtotime($session['session_date'])); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <select name="status" class="status-select" onchange="this.form.submit()">
                                        <option value="pending" <?php echo $session['status']=='pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $session['status']=='confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="completed" <?php echo $session['status']=='completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $session['status']=='cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </form>
                            </td>
                            <td><?php echo $session['meeting_link'] ? '<a href="'.htmlspecialchars($session['meeting_link']).'" target="_blank" style="color:#10b981;">🔗 View</a>' : '—'; ?></td>
                            <td>
                                <a href="edit_session.php?id=<?php echo $session['id']; ?>" class="action-btn btn-edit">Edit</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete session?');">
                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                    <input type="hidden" name="delete_session" value="1">
                                    <button type="submit" class="action-btn btn-delete">Delete</button>
                                </form>
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
            $('#sessionsTable').DataTable({
                pageLength: 25,
                responsive: true
            });
        });
    </script>
</body>
</html>
