<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
checkAccess(['student']);
require_once '../includes/user_helpers.php';

// Get student ID
$studentId = getStudentId($pdo, $_SESSION['user_id']);

// Get tutors with active sessions for this student and their materials
$tutor_materials = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT tm.*, u.name as tutor_name, t.id as tutor_profile_id 
        FROM tutor_materials tm 
        JOIN users u ON tm.tutor_id = u.id
        JOIN tutors t ON tm.tutor_id = t.user_id
        JOIN sessions s ON t.id = s.tutor_id 
        WHERE s.student_id = ? AND s.status = 'confirmed'
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
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255,255,255,0.95);
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
        .header h1 {
            margin: 0;
            font-size: 2.5rem;
        }
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
            transition: background 0.2s;
        }
        .nav a:hover {
            background: #e0f2fe;
        }
        .content {
            padding: 30px;
        }
        .tutor-materials-section {
            margin-bottom: 40px;
        }
        .tutor-materials-section h2 {
            color: #1f2937;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }
        .materials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .material-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.2s;
        }
        .material-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.1);
        }
        .material-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .tutor-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }
        .material-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
            color: #1f2937;
        }
        .material-description {
            color: #6b7280;
            margin: 12px 0;
            line-height: 1.6;
        }
        .material-meta {
            display: flex;
            gap: 20px;
            font-size: 0.9rem;
            color: #9ca3af;
        }
        .download-btn {
            background: #10b981;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .download-btn:hover {
            background: #059669;
            transform: translateY(-1px);
        }
        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }
        .resource-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .resource-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .resource-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-bottom: 15px;
        }
        .resource-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0 0 10px;
            color: #1f2937;
        }
        .resource-description {
            color: #6b7280;
            margin: 0 0 15px;
            line-height: 1.5;
        }
        .btn {
            background: #2563eb;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        .study-tips {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
            border: 1px solid #e2e8f0;
        }
        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .tip-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #2563eb;
        }
        .tip-item h4 {
            margin: 0 0 10px;
            color: #1f2937;
        }
        .tip-item p {
            margin: 0;
            color: #6b7280;
            line-height: 1.5;
        }
        .no-materials {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        @media (max-width: 768px) {
            .materials-grid {
                grid-template-columns: 1fr;
            }
            .resources-grid {
                grid-template-columns: 1fr;
            }
            .tips-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Learning Resources</h1>
            <p>Access materials from your tutors and general study tools</p>
        </div>

        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>

        <div class="content">
            <?php if (empty($tutor_materials)): ?>
            <?php else: ?>
                <div class="tutor-materials-section">
                    <h2>Your Tutor Materials (<?php echo count($tutor_materials); ?>)</h2>
                    <div class="materials-grid">
                        <?php foreach ($tutor_materials as $material): ?>
                            <div class="material-card">
                                <div class="material-header">
                                    <div class="tutor-avatar"><?php echo strtoupper(substr($material['tutor_name'], 0, 1)); ?></div>
                                    <div class="material-title"><?php echo htmlspecialchars($material['title']); ?></div>
                                </div>
                                <p class="material-description"><?php echo htmlspecialchars($material['description'] ?: 'No description provided'); ?></p>
                                <div class="material-meta">
                                    <span>By <?php echo htmlspecialchars($material['tutor_name']); ?></span>
                                    <span>Uploaded <?php echo date('M j', strtotime($material['uploaded_at'])); ?></span>
<span><?php echo strtoupper(pathinfo($material['file_name'], PATHINFO_EXTENSION)); ?></span>
                                </div>
                                <a href="../<?php echo htmlspecialchars($material['file_path']); ?>" class="download-btn" target="_blank">
                                    📥 Download Material
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="resources-grid">
                <div class="resource-card">
                    <div class="resource-icon">📚</div>
                    <h3 class="resource-title">Study Guides</h3>
                    <p class="resource-description">Access comprehensive study guides for various subjects including practice questions and key concepts.</p>
                    <a href="#" class="btn">Browse Guides</a>
                </div>

                <div class="resource-card">
                    <div class="resource-icon">🎥</div>
                    <h3 class="resource-title">Video Tutorials</h3>
                    <p class="resource-description">Watch step-by-step video explanations for complex topics taught by expert educators.</p>
                    <a href="#" class="btn">Watch Videos</a>
                </div>

                <div class="resource-card">
                    <div class="resource-icon">📝</div>
                    <h3 class="resource-title">Practice Tests</h3>
                    <p class="resource-description">Take timed practice tests to assess your knowledge and track your progress over time.</p>
                    <a href="#" class="btn">Start Test</a>
                </div>

                <div class="resource-card">
                    <div class="resource-icon">💬</div>
                    <h3 class="resource-title">Discussion Forums</h3>
                    <p class="resource-description">Connect with other students and tutors to discuss topics and share learning experiences.</p>
                    <a href="#" class="btn">Join Discussion</a>
                </div>

                <div class="resource-card">
                    <div class="resource-icon">📊</div>
                    <h3 class="resource-title">Progress Tracker</h3>
                    <p class="resource-description">Monitor your learning progress with detailed analytics and performance insights.</p>
                    <a href="#" class="btn">View Progress</a>
                </div>

                <div class="resource-card">
                    <div class="resource-icon">📰</div>
                    <h3 class="resource-title">News & Updates</h3>
                    <p class="resource-description">Stay informed about educational news, scholarship opportunities, and learning tips.</p>
                    <a href="#" class="btn">Read Updates</a>
                </div>
            </div>

            <div class="study-tips">
                <h2>Study Tips & Best Practices</h2>
                <div class="tips-grid">
                    <div class="tip-item">
                        <h4>Active Learning</h4>
                        <p>Don't just read - engage with the material by taking notes, asking questions, and teaching concepts to others.</p>
                    </div>
                    <div class="tip-item">
                        <h4>Regular Practice</h4>
                        <p>Consistent practice is key to mastery. Set aside dedicated study time each day rather than cramming.</p>
                    </div>
                    <div class="tip-item">
                        <h4>Healthy Balance</h4>
                        <p>Remember to take breaks, exercise, and get enough sleep. A healthy mind learns better.</p>
                    </div>
                    <div class="tip-item">
                        <h4>Seek Help Early</h4>
                        <p>Don't wait until you're struggling. Reach out to tutors when you first encounter difficulties.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
