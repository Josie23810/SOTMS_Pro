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
        .matches-shell {
            max-width: 1280px;
            margin: 0 auto;
        }
        .matches-content {
            padding: 24px;
        }
        .profile-summary {
            background: linear-gradient(135deg, #eff6ff, #ecfeff);
            border: 1px solid #bfdbfe;
            border-radius: 18px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.06);
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-top: 14px;
        }
        .summary-item {
            background: rgba(255,255,255,0.9);
            border-radius: 14px;
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
            background: linear-gradient(180deg, #ffffff, #fff7fb);
            border: 1px solid rgba(236, 72, 153, 0.16);
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 14px 32px rgba(15,23,42,0.07);
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
            border-radius: 22px;
            background: linear-gradient(135deg, #7c3aed, #ec4899);
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
            background: #fdf2f8;
            color: #be185d;
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
            background: #fff1f2;
            color: #9f1239;
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 0.84rem;
            font-weight: 600;
        }
        .alert {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            border-radius: 16px;
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
<body class="form-page">
    <div class="form-shell matches-shell">
        <div class="form-hero">
            <h1>Find Matching Tutors</h1>
            <p>Browse tutors ranked by your curriculum, level of study, subjects, and geographical area.</p>
        </div>

        <div class="form-nav">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="profile.php">Update My Profile</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>

        <div class="form-content matches-content">
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
                        <div class="summary-value"><?php echo htmlspecialchars($studentProfile['study_level_display'] ?? ($studentProfile['education_level_display'] ?? 'Not set')); ?></div>
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
                    <h3>No tutors matched this profile yet</h3>
                    <p>Try refining your curriculum, study level, subjects, or location so the system can match you with tutors who fit more closely.</p>
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

                            <div class="form-actions" style="margin-top:18px;">
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
