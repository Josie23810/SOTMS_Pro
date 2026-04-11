<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['student']);

ensurePlatformStructures($pdo);

$studentId = getStudentId($pdo, $_SESSION['user_id']);
$studentProfile = fetchStudentProfile($pdo, $_SESSION['user_id']);
$studentCurriculum = trim((string) ($studentProfile['curriculum_display'] ?? $studentProfile['curriculum'] ?? ''));
$studentLevel = trim((string) ($studentProfile['study_level_display'] ?? $studentProfile['level_of_study'] ?? ($studentProfile['education_level_display'] ?? $studentProfile['education_level'] ?? '')));

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
    <title>Learning Resources - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .resources-shell {
            max-width: 1280px;
            margin: 0 auto;
        }
        .resources-content {
            padding: 24px;
        }
        .summary-banner {
            background: linear-gradient(135deg, #eff6ff, #f5f3ff);
            border: 1px solid #c4b5fd;
            border-radius: 18px;
            padding: 18px;
            margin-bottom: 24px;
        }
        .summary-title {
            margin: 0 0 8px;
            font-size: 1.05rem;
            font-weight: 800;
            color: #312e81;
        }
        .summary-copy {
            margin: 0;
            color: #475569;
        }
        .profile-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }
        .profile-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 7px 11px;
            background: #fff;
            border: 1px solid #c7d2fe;
            color: #3730a3;
            font-size: 0.84rem;
            font-weight: 700;
        }
        .resource-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 18px;
        }
        .resource-card {
            background: linear-gradient(180deg, #ffffff, #fff7fb);
            border: 1px solid rgba(236, 72, 153, 0.14);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.06);
        }
        .resource-head {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: flex-start;
        }
        .resource-title {
            margin: 0;
            font-size: 1.16rem;
            font-weight: 800;
            color: #111827;
        }
        .resource-meta {
            margin-top: 8px;
            color: #475569;
            line-height: 1.7;
        }
        .resource-copy {
            margin: 14px 0 0;
            color: #475569;
            line-height: 1.7;
        }
        .tag-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }
        .tag {
            background: #fdf2f8;
            color: #be185d;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .download-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 18px;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: #fff;
            padding: 11px 16px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
        }
        .empty-card {
            text-align: center;
            padding: 56px 20px;
            background: linear-gradient(180deg, #ffffff, #fff7fb);
            border: 1px solid rgba(236, 72, 153, 0.14);
            border-radius: 20px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.06);
        }
        .empty-card h3 {
            margin: 0 0 10px;
            font-size: 1.5rem;
        }
    </style>
</head>
<body class="form-page">
    <div class="form-shell resources-shell">
        <div class="form-hero">
            <h1>Learning Resources</h1>
            <p>Open tutor-shared files tied to your sessions and aligned to your current learning profile.</p>
        </div>

        <div class="form-nav">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="schedule.php">My Sessions</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>

        <div class="form-content resources-content">
            <div class="summary-banner">
                <h2 class="summary-title">Your current learning profile</h2>
                <p class="summary-copy">These details help tutors upload materials that fit your current stage and curriculum.</p>
                <div class="profile-pills">
                    <span class="profile-pill">Curriculum: <?php echo htmlspecialchars($studentCurriculum ?: 'Not set'); ?></span>
                    <span class="profile-pill">Study level: <?php echo htmlspecialchars($studentLevel ?: 'Not set'); ?></span>
                </div>
            </div>

            <?php if (empty($tutor_materials)): ?>
                <div class="empty-card">
                    <h3>No tutor materials yet</h3>
                    <p>Resources will appear here when a tutor you have booked with uploads materials for your sessions.</p>
                </div>
            <?php else: ?>
                <div class="resource-grid">
                    <?php foreach ($tutor_materials as $material): ?>
                        <article class="resource-card">
                            <div class="resource-head">
                                <div>
                                    <h3 class="resource-title"><?php echo htmlspecialchars($material['title']); ?></h3>
                                    <div class="resource-meta">
                                        By <?php echo htmlspecialchars($material['tutor_name']); ?><br>
                                        Added on <?php echo date('M j, Y', strtotime($material['uploaded_at'])); ?>
                                    </div>
                                </div>
                            </div>

                            <p class="resource-copy"><?php echo htmlspecialchars($material['description'] ?: 'No description provided.'); ?></p>

                            <div class="tag-row">
                                <?php foreach ([$material['subject'], $material['curriculum'], $material['study_level']] as $tagValue): ?>
                                    <?php if (!empty($tagValue)): ?>
                                        <span class="tag"><?php echo htmlspecialchars($tagValue); ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>

                            <a href="../<?php echo htmlspecialchars($material['file_path']); ?>" class="download-btn" target="_blank">Download Material</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
