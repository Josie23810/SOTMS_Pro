<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['student']);

ensurePlatformStructures($pdo);

$studentId = getStudentId($pdo, $_SESSION['user_id']);
$studentProfile = fetchStudentProfile($pdo, $_SESSION['user_id']);
$studentCurriculum = trim((string) ($studentProfile['curriculum'] ?? ''));
$studentLevel = trim((string) ($studentProfile['level_of_study'] ?? ($studentProfile['education_level'] ?? '')));

$tutor_materials = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            tm.*,
            u.name AS tutor_name
        FROM tutor_materials tm
        JOIN users u ON tm.tutor_id = u.id
        JOIN tutors t ON tm.tutor_id = t.user_id
        JOIN sessions s ON t.id = s.tutor_id
        WHERE s.student_id = ?
          AND s.status IN ('pending', 'confirmed', 'completed')
        ORDER BY tm.uploaded_at DESC
    ");
    $stmt->execute([$studentId]);
    $tutor_materials = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Tutor materials fetch error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(180deg, rgba(15,23,42,0.55), rgba(15,23,42,0.55)),
                        url('../uploads/image003.jpg') center/cover no-repeat;
            color: #1f2937;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1240px;
            margin: 0 auto;
            background: rgba(255,255,255,0.96);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(15,23,42,0.15);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 { margin: 0; font-size: 2.5rem; }
        .nav {
            background: #f8fafc;
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
        }
        .nav a {
            color: #2563eb;
            text-decoration: none;
            margin: 0 15px;
            font-weight: 600;
            padding: 10px 15px;
            border-radius: 8px;
        }
        .nav a:hover { background: #e0f2fe; }
        .content { padding: 30px; }
        .summary {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 24px;
        }
        .materials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
        }
        .material-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 8px 20px rgba(15,23,42,0.05);
        }
        .material-header { display: flex; justify-content: space-between; gap: 14px; }
        .material-title { font-size: 1.2rem; font-weight: 700; margin: 0; }
        .material-description { color: #6b7280; margin: 12px 0; line-height: 1.6; }
        .tag-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .tag { background: #dbeafe; color: #1d4ed8; padding: 6px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
        .download-btn {
            background: #10b981;
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            margin-top: 16px;
        }
        .download-btn:hover { background: #059669; }
        .empty-state { text-align: center; padding: 60px 20px; color: #6b7280; }
        @media (max-width: 768px) {
            .materials-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Learning Materials</h1>
            <p>Access tutor-uploaded resources connected to your sessions and aligned with your study profile.</p>
        </div>

        <div class="nav">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="schedule.php">My Sessions</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>

        <div class="content">
            <div class="summary">
                <strong>Your current learning profile:</strong>
                Curriculum: <?php echo htmlspecialchars($studentCurriculum ?: 'Not set'); ?> |
                Study level: <?php echo htmlspecialchars($studentLevel ?: 'Not set'); ?>
            </div>

            <?php if (empty($tutor_materials)): ?>
                <div class="empty-state">
                    <h3>No tutor materials yet</h3>
                    <p>Resources appear here once a tutor you have booked with uploads learning materials.</p>
                </div>
            <?php else: ?>
                <div class="materials-grid">
                    <?php foreach ($tutor_materials as $material): ?>
                        <div class="material-card">
                            <div class="material-header">
                                <div>
                                    <h3 class="material-title"><?php echo htmlspecialchars($material['title']); ?></h3>
                                    <div style="color:#475569; margin-top:6px;">By <?php echo htmlspecialchars($material['tutor_name']); ?></div>
                                </div>
                                <div style="color:#94a3b8; font-size:0.9rem;"><?php echo date('M j, Y', strtotime($material['uploaded_at'])); ?></div>
                            </div>
                            <p class="material-description"><?php echo htmlspecialchars($material['description'] ?: 'No description provided.'); ?></p>
                            <div class="tag-row">
                                <?php foreach ([$material['subject'], $material['curriculum'], $material['study_level']] as $tagValue): ?>
                                    <?php if (!empty($tagValue)): ?>
                                        <span class="tag"><?php echo htmlspecialchars($tagValue); ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <a href="../<?php echo htmlspecialchars($material['file_path']); ?>" class="download-btn" target="_blank">Download Material</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
