<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
checkAccess(['student']);

// Get available tutors + profiles
$tutors = [];
try {
    $stmt = $pdo->prepare('SELECT u.id AS user_id, t.id AS tutor_id, u.name, u.email, tp.profile_image, tp.subjects_taught, tp.qualifications, tp.bio, tp.experience, tp.hourly_rate
        FROM users u
        LEFT JOIN tutors t ON t.user_id = u.id
        LEFT JOIN tutor_profiles tp ON tp.user_id = u.id
        WHERE u.role = "tutor"
        ORDER BY u.name');
    $stmt->execute();
    $tutors = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Tutors fetch error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Tutors - SOTMS PRO</title>
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
        .tutors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .tutor-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .tutor-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .tutor-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            margin: 0 auto 15px;
        }
        .tutor-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0 0 10px;
            color: #1f2937;
        }
        .tutor-email {
            color: #6b7280;
            margin-bottom: 15px;
        }
        .btn {
            background: #2563eb;
            color: white !important;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        .btn-secondary {
            background: #6b7280;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .no-tutors {
            text-align: center;
            padding: 50px;
            color: #6b7280;
        }
        @media (max-width: 768px) {
            .tutors-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Find Tutors</h1>
            <p>Connect with qualified tutors for your learning needs</p>
        </div>

        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>

        <div class="content">
            <?php if (empty($tutors)): ?>
                <div class="no-tutors">
                    <h3>No tutors available</h3>
                    <p>We're working on expanding our tutor network. Please check back soon!</p>
                </div>
            <?php else: ?>
                <div class="tutors-grid">
                    <?php foreach ($tutors as $tutor): ?>
                        <div class="tutor-card">
                            <div class="tutor-avatar">
                                <?php if (!empty($tutor['profile_image'])): ?>
                                    <img src="../<?php echo htmlspecialchars($tutor['profile_image']); ?>" alt="<?php echo htmlspecialchars($tutor['name']); ?>" style="border-radius:50%; width:80px; height:80px; object-fit:cover;">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($tutor['name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <h3 class="tutor-name"><?php echo htmlspecialchars($tutor['name']); ?></h3>
                            <div class="tutor-email"><?php echo htmlspecialchars($tutor['email']); ?></div>
                            <?php if (!empty($tutor['subjects_taught'])): ?>
                                <p><strong>Subjects:</strong> <?php echo htmlspecialchars($tutor['subjects_taught']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($tutor['hourly_rate'])): ?>
                                <p><strong>Rate:</strong> <?php echo htmlspecialchars($tutor['hourly_rate']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($tutor['bio'])): ?>
                                <p style="font-size:0.95rem; color:#475569; margin-top:10px;"><?php echo nl2br(htmlspecialchars(substr($tutor['bio'], 0, 200))); ?><?php echo strlen($tutor['bio']) > 200 ? '...' : ''; ?></p>
                            <?php endif; ?>
                            <div style="margin-top: 15px;">
                                <a href="messages.php?to=<?php echo $tutor['user_id']; ?>" class="btn">Send Message</a>
                                <a href="book_session.php?tutor=<?php echo $tutor['tutor_id']; ?>" class="btn" style="margin-left: 10px; background: #2563eb !important; color: white !important;">Book Session</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>