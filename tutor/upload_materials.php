<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
checkAccess(['tutor']);

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tutor_materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tutor_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        description TEXT,
        file_path VARCHAR(255) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    error_log('Material table creation error: '.$e->getMessage());
}

$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if (empty($title)) {
        $message = 'Title is required.';
        $messageType = 'error';
    } elseif (!isset($_FILES['material_file']) || $_FILES['material_file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Please upload a file.';
        $messageType = 'error';
    } else {
        $uploadDir = '../uploads/tutor_materials/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $originalName = basename($_FILES['material_file']['name']);
        $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['pdf','doc','docx','ppt','pptx','zip','png','jpg','jpeg'];
        if (!in_array($fileExtension, $allowed)) {
            $message = 'Allowed file types: PDF, DOC, PPT, ZIP, PNG, JPG.';
            $messageType = 'error';
        } else {
            $targetName = 'material_' . $_SESSION['user_id'] . '_' . time() . '.' . $fileExtension;
            $targetPath = $uploadDir . $targetName;
            if (move_uploaded_file($_FILES['material_file']['tmp_name'], $targetPath)) {
                try {
                    $stmt = $pdo->prepare('INSERT INTO tutor_materials (tutor_id, title, description, file_path, file_name) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$_SESSION['user_id'], $title, $description, 'uploads/tutor_materials/'.$targetName, $originalName]);
                    $message = 'Material uploaded successfully.';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Could not save the material.';
                    $messageType = 'error';
                    error_log('Material save error: '.$e->getMessage());
                }
            } else {
                $message = 'Failed to move uploaded file.';
                $messageType = 'error';
            }
        }
    }
}

$materials = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM tutor_materials WHERE tutor_id = ? ORDER BY uploaded_at DESC');
    $stmt->execute([$_SESSION['user_id']]);
    $materials = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Material fetch error: '.$e->getMessage());
}

// Handle delete
if (isset($_POST['delete_id'])) {
    $deleteId = intval($_POST['delete_id']);
    try {
        $stmt = $pdo->prepare('SELECT file_path FROM tutor_materials WHERE id = ? AND tutor_id = ?');
        $stmt->execute([$deleteId, $_SESSION['user_id']]);
        $material = $stmt->fetch();
        if ($material) {
            unlink('../' . $material['file_path']);
            $stmt = $pdo->prepare('DELETE FROM tutor_materials WHERE id = ? AND tutor_id = ?');
            $stmt->execute([$deleteId, $_SESSION['user_id']]);
            $message = 'Material deleted successfully.';
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Delete failed.';
        $messageType = 'error';
    }
}

// Handle edit (simple - reload form prefilled)
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $pdo->prepare('SELECT * FROM tutor_materials WHERE id = ? AND tutor_id = ?');
    $stmt->execute([$editId, $_SESSION['user_id']]);
    $editMaterial = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Materials - Tutor Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family:'Poppins',sans-serif; background: linear-gradient(180deg,rgba(15,23,42,0.6),rgba(15,23,42,0.6)),url('../uploads/image003.jpg') center/cover no-repeat; color:#1f2937; margin:0; padding:20px; }
        .container { max-width:1100px; margin:0 auto; background:rgba(255,255,255,0.96); border-radius:20px; overflow:hidden; box-shadow:0 24px 50px rgba(15,23,42,0.18);} 
        .header { background: linear-gradient(135deg,#2563eb,#1d4ed8); color:white; padding:30px; }
        .header h1 { margin:0; font-size:2.4rem; }
        .nav { padding:20px; background:#f8fafc; border-bottom:1px solid #e5e7eb; }
        .nav a { color:#2563eb; margin-right:18px; text-decoration:none; font-weight:600; }
        .content { padding:30px; }
        .form-card { background:white; border-radius:18px; border:1px solid #e5e7eb; padding:28px; box-shadow:0 12px 28px rgba(15,23,42,0.08); margin-bottom:30px; }
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; margin-bottom:8px; font-weight:700; color:#1f2937; }
        .form-group input, .form-group textarea { width:100%; padding:14px; border:1px solid #d1d5db; border-radius:12px; font-size:1rem; }
        .form-group textarea { min-height:120px; resize:vertical; }
        .btn { display:inline-flex; align-items:center; justify-content:center; background:#2563eb; color:white; border:none; border-radius:12px; padding:12px 20px; font-weight:700; text-decoration:none; cursor:pointer; margin-right:10px; }
        .btn:hover { background:#1d4ed8; }
        .btn-danger { background:#ef4444; }
        .btn-danger:hover { background:#dc2626; }
        .btn-edit { background:#10b981; }
        .btn-edit:hover { background:#059669; }
        .message { padding:16px; border-radius:14px; margin-bottom:20px; }
        .success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
        .error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        .material-item { background:#f9fafb; border:1px solid #e5e7eb; border-radius:16px; padding:18px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; gap:20px; }
        .material-actions { display:flex; gap:8px; }
        .material-meta { color:#475569; margin-top:8px; }
        .material-title { margin:0; font-size:1.05rem; }
        @media(max-width:768px){ .material-item{flex-direction:column; align-items:flex-start;} .nav a{display:block; margin-bottom:10px;} .material-actions{flex-direction:column;} }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Manage Teaching Materials</h1>
            <p>Upload, edit, or delete resources for your students</p>
        </div>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="schedule.php">Schedule</a>
            <a href="messages.php">Messages</a>
            <a href="profile.php">Profile</a>
            <a href="settings.php">Settings</a>
        </div>
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <div class="form-card">
                <h3><?php echo isset($editMaterial) ? 'Edit Material' : 'Upload New Material'; ?></h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="material_id" value="<?php echo $editMaterial['id'] ?? ''; ?>">
                    <div class="form-group">
                        <label for="title">Material Title</label>
                        <input type="text" id="title" name="title" value="<?php echo $editMaterial['title'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description"><?php echo $editMaterial['description'] ?? ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="material_file">Upload File <?php echo isset($editMaterial) ? '(Leave empty to keep current)' : ''; ?></label>
                        <input type="file" id="material_file" name="material_file" <?php echo !isset($editMaterial) ? 'required' : ''; ?>>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="action" value="<?php echo isset($editMaterial) ? 'update' : 'upload'; ?>" class="btn btn-<?php echo isset($editMaterial) ? 'edit' : 'primary'; ?>">
                            <?php echo isset($editMaterial) ? 'Update Material' : 'Upload Material'; ?>
                        </button>
                        <?php if (isset($editMaterial)): ?>
                            <a href="?clear_edit=1" class="btn btn-secondary">Cancel Edit</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="form-card">
                <h2>Your Materials (<?php echo count($materials); ?>)</h2>
                <?php if (empty($materials)): ?>
                    <p style="color:#475569;">No materials uploaded yet. Upload your first resource above.</p>
                <?php else: ?>
<?php foreach ($materials as $item): ?>
                        <div class="material-item">
                            <div>
                                <p class="material-title"><?php echo htmlspecialchars($item['title']); ?></p>
                                <p class="material-meta"><?php echo htmlspecialchars($item['description'] ?: 'No description provided'); ?></p>
                                <p class="material-meta">Uploaded: <?php echo date('M j, Y', strtotime($item['uploaded_at'])); ?></p>
                            </div>
                            <div class="material-actions">
                                <a href="?edit=<?php echo $item['id']; ?>" class="btn btn-edit">Edit</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this material?')">
                                    <input type="hidden" name="delete_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                                <a href="../<?php echo htmlspecialchars($item['file_path']); ?>" class="btn" target="_blank" title="Preview">Preview</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

