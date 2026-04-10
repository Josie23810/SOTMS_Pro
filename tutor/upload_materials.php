<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require_once '../includes/upload_helpers.php';
checkAccess(['tutor']);

ensurePlatformStructures($pdo);

$message = '';
$messageType = '';
$editMaterial = null;

if (isset($_POST['delete_id'])) {
    $deleteId = intval($_POST['delete_id']);
    try {
        $stmt = $pdo->prepare('SELECT file_path FROM tutor_materials WHERE id = ? AND tutor_id = ?');
        $stmt->execute([$deleteId, $_SESSION['user_id']]);
        $material = $stmt->fetch();
        if ($material) {
            $absolutePath = '../' . $material['file_path'];
            if (is_file($absolutePath)) {
                unlink($absolutePath);
            }
            $stmt = $pdo->prepare('DELETE FROM tutor_materials WHERE id = ? AND tutor_id = ?');
            $stmt->execute([$deleteId, $_SESSION['user_id']]);
            $message = 'Material deleted successfully.';
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Delete failed.';
        $messageType = 'error';
        error_log('Tutor material delete error: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['upload', 'update'], true)) {
    $materialId = intval($_POST['material_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $curriculum = trim($_POST['curriculum'] ?? '');
    $study_level = trim($_POST['study_level'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '') {
        $message = 'Title is required.';
        $messageType = 'error';
    } else {
        $hasFile = isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK;
        $storedFilePath = null;
        $storedFileName = null;

        if ($hasFile) {
            try {
                $upload = saveUploadedFile(
                    'material_file',
                    dirname(__DIR__) . '/uploads/tutor_materials',
                    'uploads/tutor_materials',
                    ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'png', 'jpg', 'jpeg'],
                    [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'application/zip',
                        'application/x-zip-compressed',
                        'image/jpeg',
                        'image/png'
                    ],
                    'material_' . $_SESSION['user_id'],
                    12 * 1024 * 1024
                );
                $storedFilePath = $upload['path'];
                $storedFileName = $upload['original_name'];
            } catch (RuntimeException $e) {
                $message = $e->getMessage();
                $messageType = 'error';
            }
        } elseif ($_POST['action'] === 'upload') {
            $message = 'Please upload a file.';
            $messageType = 'error';
        }

        if ($message === '') {
            try {
                if ($_POST['action'] === 'update' && $materialId > 0) {
                    $stmt = $pdo->prepare('SELECT file_path, file_name FROM tutor_materials WHERE id = ? AND tutor_id = ?');
                    $stmt->execute([$materialId, $_SESSION['user_id']]);
                    $current = $stmt->fetch();

                    if (!$current) {
                        throw new PDOException('Material not found for update.');
                    }

                    if (!$storedFilePath) {
                        $storedFilePath = $current['file_path'];
                        $storedFileName = $current['file_name'];
                    } else {
                        deleteRelativeFileIfExists($current['file_path']);
                    }

                    $stmt = $pdo->prepare('
                        UPDATE tutor_materials
                        SET title = ?, subject = ?, curriculum = ?, study_level = ?, description = ?, file_path = ?, file_name = ?
                        WHERE id = ? AND tutor_id = ?
                    ');
                    $stmt->execute([$title, $subject, $curriculum, $study_level, $description, $storedFilePath, $storedFileName, $materialId, $_SESSION['user_id']]);
                    $message = 'Material updated successfully.';
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO tutor_materials (tutor_id, title, subject, curriculum, study_level, description, file_path, file_name)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([$_SESSION['user_id'], $title, $subject, $curriculum, $study_level, $description, $storedFilePath, $storedFileName]);
                    $message = 'Material uploaded successfully.';
                }

                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Could not save the material.';
                $messageType = 'error';
                error_log('Tutor material save error: ' . $e->getMessage());
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $pdo->prepare('SELECT * FROM tutor_materials WHERE id = ? AND tutor_id = ?');
    $stmt->execute([$editId, $_SESSION['user_id']]);
    $editMaterial = $stmt->fetch();
}

$materials = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM tutor_materials WHERE tutor_id = ? ORDER BY uploaded_at DESC');
    $stmt->execute([$_SESSION['user_id']]);
    $materials = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Tutor material fetch error: ' . $e->getMessage());
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
        .container { max-width:1120px; margin:0 auto; background:rgba(255,255,255,0.96); border-radius:20px; overflow:hidden; box-shadow:0 24px 50px rgba(15,23,42,0.18);}
        .header { background: linear-gradient(135deg,#2563eb,#1d4ed8); color:white; padding:30px; }
        .header h1 { margin:0; font-size:2.4rem; }
        .nav { padding:20px; background:#f8fafc; border-bottom:1px solid #e5e7eb; }
        .nav a { color:#2563eb; margin-right:18px; text-decoration:none; font-weight:600; }
        .content { padding:30px; }
        .form-card { background:white; border-radius:18px; border:1px solid #e5e7eb; padding:28px; box-shadow:0 12px 28px rgba(15,23,42,0.08); margin-bottom:30px; }
        .form-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:18px; }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; margin-bottom:8px; font-weight:700; color:#1f2937; }
        .form-group input, .form-group textarea { width:100%; padding:14px; border:1px solid #d1d5db; border-radius:12px; font-size:1rem; box-sizing:border-box; }
        .form-group textarea { min-height:110px; resize:vertical; }
        .btn { display:inline-flex; align-items:center; justify-content:center; background:#2563eb; color:white; border:none; border-radius:12px; padding:12px 20px; font-weight:700; text-decoration:none; cursor:pointer; margin-right:10px; }
        .btn:hover { background:#1d4ed8; }
        .btn-danger { background:#ef4444; }
        .btn-danger:hover { background:#dc2626; }
        .btn-edit { background:#10b981; }
        .btn-edit:hover { background:#059669; }
        .btn-secondary { background:#475569; }
        .btn-secondary:hover { background:#334155; }
        .message { padding:16px; border-radius:14px; margin-bottom:20px; }
        .success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
        .error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        .material-item { background:#f9fafb; border:1px solid #e5e7eb; border-radius:16px; padding:18px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:flex-start; gap:20px; }
        .material-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .material-meta { color:#475569; margin-top:8px; line-height:1.6; }
        .material-title { margin:0; font-size:1.05rem; }
        .tag-row { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
        .tag { background:#dbeafe; color:#1d4ed8; border-radius:999px; padding:6px 10px; font-size:0.82rem; font-weight:600; }
        @media(max-width:768px){ .material-item, .form-grid{grid-template-columns:1fr; display:block;} .nav a{display:block; margin-bottom:10px;} .material-actions{flex-direction:column;} }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Manage Teaching Materials</h1>
            <p>Upload materials tagged by subject, curriculum, and study level so students can access what fits them best.</p>
        </div>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="schedule.php">Schedule</a>
            <a href="messages.php">Messages</a>
            <a href="profile.php">Profile</a>
        </div>
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <div class="form-card">
                <h3><?php echo $editMaterial ? 'Edit Material' : 'Upload New Material'; ?></h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="material_id" value="<?php echo (int) ($editMaterial['id'] ?? 0); ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="title">Material Title</label>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($editMaterial['title'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($editMaterial['subject'] ?? ''); ?>" placeholder="e.g. Mathematics">
                        </div>
                        <div class="form-group">
                            <label for="curriculum">Curriculum</label>
                            <input type="text" id="curriculum" name="curriculum" value="<?php echo htmlspecialchars($editMaterial['curriculum'] ?? ''); ?>" placeholder="e.g. CBC, IGCSE">
                        </div>
                        <div class="form-group">
                            <label for="study_level">Study Level</label>
                            <input type="text" id="study_level" name="study_level" value="<?php echo htmlspecialchars($editMaterial['study_level'] ?? ''); ?>" placeholder="e.g. Grade 8, Form 4">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description"><?php echo htmlspecialchars($editMaterial['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="material_file">Upload File <?php echo $editMaterial ? '(leave empty to keep the current file)' : ''; ?></label>
                        <input type="file" id="material_file" name="material_file" <?php echo !$editMaterial ? 'required' : ''; ?>>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="action" value="<?php echo $editMaterial ? 'update' : 'upload'; ?>" class="btn">
                            <?php echo $editMaterial ? 'Update Material' : 'Upload Material'; ?>
                        </button>
                        <?php if ($editMaterial): ?>
                            <a href="upload_materials.php" class="btn btn-secondary">Cancel Edit</a>
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
                                <div class="tag-row">
                                    <?php foreach ([$item['subject'], $item['curriculum'], $item['study_level']] as $tagValue): ?>
                                        <?php if (!empty($tagValue)): ?>
                                            <span class="tag"><?php echo htmlspecialchars($tagValue); ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <p class="material-meta">Uploaded: <?php echo date('M j, Y', strtotime($item['uploaded_at'])); ?> | File: <?php echo htmlspecialchars($item['file_name']); ?></p>
                            </div>
                            <div class="material-actions">
                                <a href="upload_materials.php?edit=<?php echo (int) $item['id']; ?>" class="btn btn-edit">Edit</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this material?')">
                                    <input type="hidden" name="delete_id" value="<?php echo (int) $item['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                                <a href="../<?php echo htmlspecialchars($item['file_path']); ?>" class="btn" target="_blank">Preview</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
