<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require_once '../includes/services/TutorMatchService.php';
checkAccess(['student']);

ensurePlatformStructures($pdo);

list($studentProfile, $tutors) = TutorMatchService::getMatches($pdo, $_SESSION['user_id']);
$profileReady = !empty($studentProfile['curriculum_display']) || !empty($studentProfile['study_level_display']) || !empty($studentProfile['location']) || !empty($studentProfile['subjects_display']);
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
            max-width: 1280px;
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
        .header p { margin: 10px 0 0; opacity: 0.92; }
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
        .profile-summary {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-top: 14px;
        }
        .summary-item {
            background: white;
            border-radius: 12px;
            padding: 14px;
            border: 1px solid #dbeafe;
        }
        .summary-label {
            font-size: 0.82rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .summary-value {
            margin-top: 6px;
            font-weight: 700;
            color: #0f172a;
        }
        .tutors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .tutor-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 8px 24px rgba(15,23,42,0.06);
        }
        .tutor-head {
            display: flex;
            gap: 14px;
            align-items: center;
            margin-bottom: 16px;
        }
        .tutor-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 700;
            overflow: hidden;
        }
        .tutor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .match-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 0.84rem;
            font-weight: 700;
        }
        .detail {
            color: #475569;
            margin: 7px 0;
            line-height: 1.55;
        }
        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .tag {
            background: #f1f5f9;
            color: #334155;
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 0.84rem;
            font-weight: 600;
        }
        .btn {
            background: #2563eb;
            color: white !important;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover { background: #1d4ed8; }
        .btn-secondary { background: #0f766e; }
        .btn-secondary:hover { background: #115e59; }
        .alert {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 24px;
        }
        .no-tutors {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        @media (max-width: 768px) {
            .summary-grid,
            .tutors-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Find Matching Tutors</h1>
            <p>Browse tutors ranked by your curriculum, level of study, subjects, and geographical area.</p>
        </div>

        <div class="nav">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="profile.php">Update My Profile</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>

        <div class="content">
            <?php if (!$profileReady): ?>
                <div class="alert">
                    Complete your profile with your curriculum, study level, subjects, and location to improve tutor matching.
                    <a href="profile.php" class="btn" style="margin-left:12px;">Complete Profile</a>
                </div>
            <?php endif; ?>

            <div class="profile-summary">
                <strong>Your matching profile</strong>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Curriculum</div>
                        <div class="summary-value"><?php echo htmlspecialchars($studentProfile['curriculum_display'] ?? 'Not set'); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Level of Study</div>
                        <div class="summary-value"><?php echo htmlspecialchars($studentProfile['study_level_display'] ?? ($studentProfile['education_level'] ?? 'Not set')); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Location</div>
                        <div class="summary-value"><?php echo htmlspecialchars($studentProfile['location'] ?? 'Not set'); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Subjects</div>
                        <div class="summary-value"><?php echo htmlspecialchars($studentProfile['subjects_display'] ?? 'Not set'); ?></div>
                    </div>
                </div>
            </div>

            <?php if (empty($tutors)): ?>
                <div class="no-tutors">
                    <h3>No tutors available</h3>
                    <p>We could not find tutor profiles yet. Tutors will appear here once they complete their profiles.</p>
                </div>
            <?php else: ?>
                <div class="tutors-grid">
                    <?php foreach ($tutors as $tutor): ?>
                        <div class="tutor-card">
                            <div class="tutor-head">
                                <div class="tutor-avatar">
                                    <?php if (!empty($tutor['profile_image'])): ?>
                                        <img src="../<?php echo htmlspecialchars($tutor['profile_image']); ?>" alt="<?php echo htmlspecialchars($tutor['name']); ?>">
                                    <?php else: ?>
                                        <?php echo htmlspecialchars(strtoupper(substr($tutor['name'], 0, 1))); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 style="margin:0;"><?php echo htmlspecialchars($tutor['full_name'] ?: $tutor['name']); ?></h3>
                                    <div class="detail"><?php echo htmlspecialchars($tutor['display_email']); ?></div>
                                    <span class="match-badge">Match Score <?php echo (int) $tutor['match_score']; ?></span>
                                </div>
                            </div>

                            <div class="detail"><strong>Subjects:</strong> <?php echo htmlspecialchars($tutor['subjects_taught_display'] ?: 'Not provided'); ?></div>
                            <div class="detail"><strong>Curriculum:</strong> <?php echo htmlspecialchars($tutor['curriculum_specialties_display'] ?: 'Not provided'); ?></div>
                            <div class="detail"><strong>Levels:</strong> <?php echo htmlspecialchars($tutor['study_levels_supported_display'] ?: 'Not provided'); ?></div>
                            <div class="detail"><strong>Location:</strong> <?php echo htmlspecialchars($tutor['location'] ?: 'Not provided'); ?></div>
                            <div class="detail"><strong>Service Areas:</strong> <?php echo htmlspecialchars($tutor['service_areas_display'] ?: 'Not provided'); ?></div>
                            <div class="detail"><strong>Qualifications:</strong> <?php echo htmlspecialchars($tutor['qualifications'] ?: 'Not provided'); ?></div>
                            <div class="detail"><strong>Rate:</strong> KSh <?php echo number_format($tutor['session_rate'], 2); ?></div>
                            <?php if (!empty($tutor['bio'])): ?>
                                <div class="detail"><?php echo nl2br(htmlspecialchars(substr($tutor['bio'], 0, 180))); ?><?php echo strlen($tutor['bio']) > 180 ? '...' : ''; ?></div>
                            <?php endif; ?>

                            <div class="tag-list">
                                <?php if (!empty($tutor['match_reasons'])): ?>
                                    <?php foreach ($tutor['match_reasons'] as $reason): ?>
                                        <span class="tag"><?php echo htmlspecialchars($reason); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="tag">General availability</span>
                                <?php endif; ?>
                            </div>

                            <div style="margin-top: 18px; display:flex; gap:10px; flex-wrap:wrap;">
                                <a href="messages.php?to=<?php echo (int) $tutor['user_id']; ?>" class="btn btn-secondary">Send Message</a>
                                <a href="book_session.php?tutor=<?php echo (int) $tutor['tutor_id']; ?>" class="btn">Book Session</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
