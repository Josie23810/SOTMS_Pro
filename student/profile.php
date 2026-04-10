<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require_once '../includes/upload_helpers.php';
require_once '../includes/services/ProfileTaxonomyService.php';
checkAccess(['student']);

ensurePlatformStructures($pdo);
$catalogOptions = ProfileTaxonomyService::getCatalogOptions($pdo);

$educationLevelOptions = [
    'primary' => 'Primary School',
    'junior_secondary' => 'Junior Secondary',
    'high_school' => 'Senior Secondary / High School',
    'certificate' => 'Certificate',
    'diploma' => 'Diploma',
    'bachelors' => "Bachelor's Degree",
    'postgraduate_diploma' => 'Postgraduate Diploma',
    'masters' => "Master's Degree",
    'phd' => 'PhD / Doctorate',
    'professional' => 'Professional Qualification',
    'other' => 'Other'
];

$studyLevelSuggestions = [
    'Grade 4', 'Grade 5', 'Grade 6',
    'Grade 7', 'Grade 8', 'Grade 9',
    'Form 1', 'Form 2', 'Form 3', 'Form 4',
    'Certificate Year 1', 'Certificate Year 2',
    'Diploma Year 1', 'Diploma Year 2', 'Diploma Final Year',
    "Bachelor's Year 1", "Bachelor's Year 2", "Bachelor's Year 3", "Bachelor's Year 4",
    'Postgraduate Diploma',
    "Master's Coursework", "Master's Research",
    'PhD Year 1', 'PhD Candidate',
    'CPA', 'ACCA', 'Professional Certification'
];

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $education_level = trim($_POST['education_level'] ?? 'high_school');
    $level_of_study = trim($_POST['level_of_study'] ?? '');
    $current_institution = trim($_POST['current_institution'] ?? '');
    $curriculum = trim($_POST['curriculum'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $guardian_name = trim($_POST['guardian_name'] ?? '');
    $guardian_phone = trim($_POST['guardian_phone'] ?? '');
    $subjects_interested = trim($_POST['subjects_interested'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $goals = trim($_POST['goals'] ?? '');
    $preferred_radius_km = max(5, min(500, intval($_POST['preferred_radius_km'] ?? 50)));

    $profile_image = '';
    if (empty($message)) {
        try {
            $upload = saveUploadedFile(
                'profile_image',
                dirname(__DIR__) . '/uploads/profiles',
                'uploads/profiles',
                ['jpg', 'jpeg', 'png', 'gif'],
                ['image/jpeg', 'image/png', 'image/gif'],
                'profile_' . $_SESSION['user_id'],
                5 * 1024 * 1024
            );
            if ($upload) {
                $profile_image = $upload['path'];
            }
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }

    if (empty($message)) {
        try {
            $stmt = $pdo->prepare('SELECT id FROM student_profiles WHERE user_id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $existing = $stmt->fetch();

            if ($existing) {
                $sql = 'UPDATE student_profiles
                        SET full_name = ?, phone = ?, education_level = ?, level_of_study = ?, current_institution = ?, curriculum = ?, location = ?, guardian_name = ?, guardian_phone = ?, subjects_interested = ?, bio = ?, goals = ?, preferred_radius_km = ?';
                $params = [$full_name, $phone, $education_level, $level_of_study, $current_institution, $curriculum, $location, $guardian_name, $guardian_phone, $subjects_interested, $bio, $goals, $preferred_radius_km];

                if (!empty($profile_image)) {
                    $sql .= ', profile_image = ?';
                    $params[] = $profile_image;
                }

                $sql .= ' WHERE user_id = ?';
                $params[] = $_SESSION['user_id'];

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO student_profiles (
                        user_id, profile_image, full_name, phone, education_level, level_of_study, current_institution,
                        curriculum, location, guardian_name, guardian_phone, subjects_interested, bio, goals, preferred_radius_km
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $_SESSION['user_id'],
                    $profile_image,
                    $full_name,
                    $phone,
                    $education_level,
                    $level_of_study,
                    $current_institution,
                    $curriculum,
                    $location,
                    $guardian_name,
                    $guardian_phone,
                    $subjects_interested,
                    $bio,
                    $goals,
                    $preferred_radius_km
                ]);
            }

            ProfileTaxonomyService::syncStudentProfile($pdo, $_SESSION['user_id'], [
                'education_level' => $education_level,
                'level_of_study' => $level_of_study,
                'curriculum' => $curriculum,
                'subjects_interested' => $subjects_interested
            ]);

            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            $message = 'Error updating profile. Please try again.';
            $messageType = 'error';
            error_log('Student profile update error: ' . $e->getMessage());
        }
    }
}

$profile = fetchStudentProfile($pdo, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student / Parent Profile - SOTMS PRO</title>
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
            max-width: 1100px;
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
        .header h1 { margin: 0; font-size: 2.4rem; }
        .header p { margin: 10px 0 0; opacity: 0.9; }
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
        .profile-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
        }
        .profile-image-section,
        .profile-form {
            background: #f8fafc;
            border-radius: 14px;
            padding: 22px;
            border: 1px solid #e2e8f0;
        }
        .current-image {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e2e8f0;
            margin-bottom: 20px;
        }
        .form-section {
            margin-bottom: 28px;
        }
        .form-section h3 {
            margin: 0 0 16px;
            color: #1f2937;
            font-size: 1.15rem;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #374151;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 15px;
            box-sizing: border-box;
            background: white;
        }
        .form-group textarea { min-height: 90px; resize: vertical; }
        .btn {
            background: #2563eb;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn:hover { background: #1d4ed8; }
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .help-text {
            color: #64748b;
            font-size: 0.92rem;
            margin-top: 6px;
        }
        @media (max-width: 768px) {
            .profile-grid,
            .form-grid {
                grid-template-columns: 1fr;
            }
            .current-image {
                width: 160px;
                height: 160px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Student / Parent Profile</h1>
            <p>Set your curriculum, study level, location, and learning goals so SOTMS Pro can recommend the right tutors.</p>
        </div>

        <div class="nav">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="find_tutors.php">Find Tutors</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="profile-grid">
                    <div class="profile-image-section">
                        <h3>Profile Picture</h3>
                        <?php if ($profile && !empty($profile['profile_image'])): ?>
                            <img src="../<?php echo htmlspecialchars($profile['profile_image']); ?>" alt="Profile Image" class="current-image">
                        <?php else: ?>
                            <div class="current-image" style="background:#e2e8f0; display:flex; align-items:center; justify-content:center; color:#6b7280; font-size:48px;">
                                <?php echo htmlspecialchars(strtoupper(substr($_SESSION['name'], 0, 1))); ?>
                            </div>
                        <?php endif; ?>
                        <div class="form-group" style="margin-top: 20px;">
                            <label for="profile_image">Upload New Image</label>
                            <input type="file" id="profile_image" name="profile_image" accept="image/*">
                            <div class="help-text">JPG, PNG, or GIF. Max 5MB.</div>
                        </div>
                    </div>

                    <div class="profile-form">
                        <div class="form-section">
                            <h3>Learner Details</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="full_name">Full Name</label>
                                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="education_level">Education Level</label>
                                    <select id="education_level" name="education_level">
                                        <?php
                                        $selectedEducation = $profile['education_level'] ?? 'high_school';
                                        if (!isset($educationLevelOptions[$selectedEducation]) && $selectedEducation !== ''):
                                        ?>
                                            <option value="<?php echo htmlspecialchars($selectedEducation); ?>" selected><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $selectedEducation))); ?> (Existing)</option>
                                        <?php
                                        endif;
                                        foreach ($educationLevelOptions as $value => $label):
                                        ?>
                                            <option value="<?php echo $value; ?>" <?php echo $selectedEducation === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="help-text">Choose the broad stage first, then specify the exact class, year, diploma, or degree level below.</div>
                                </div>
                                <div class="form-group">
                                    <label for="level_of_study">Class / Level of Study</label>
                                    <input type="text" id="level_of_study" name="level_of_study" list="study_level_options" value="<?php echo htmlspecialchars($profile['study_level_display'] ?? ($profile['level_of_study'] ?? '')); ?>" placeholder="e.g. Grade 8, Diploma Year 1, Bachelor's Year 2, Master's Coursework">
                                    <datalist id="study_level_options">
                                        <?php foreach (array_unique(array_merge($studyLevelSuggestions, $catalogOptions['study_levels'])) as $studyLevelOption): ?>
                                            <option value="<?php echo htmlspecialchars($studyLevelOption); ?>"></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                    <div class="help-text">Examples: Grade 8, Form 4, Certificate Year 1, Diploma Year 2, Bachelor's Year 3, Master's Coursework, PhD Candidate.</div>
                                </div>
                                <div class="form-group">
                                    <label for="current_institution">School / Institution</label>
                                    <input type="text" id="current_institution" name="current_institution" value="<?php echo htmlspecialchars($profile['current_institution'] ?? ''); ?>" placeholder="e.g. Alliance High School">
                                </div>
                                <div class="form-group">
                                    <label for="curriculum">Curriculum / Programme Track</label>
                                    <input type="text" id="curriculum" name="curriculum" list="curriculum_options" value="<?php echo htmlspecialchars($profile['curriculum_display'] ?? ($profile['curriculum'] ?? '')); ?>" placeholder="e.g. CBC, 8-4-4, IGCSE, KCSE, IB, TVET, University Degree">
                                    <datalist id="curriculum_options">
                                        <?php foreach (array_unique(array_merge($catalogOptions['curricula'], ['TVET', 'University Degree', 'Professional Programme'])) as $curriculumOption): ?>
                                            <option value="<?php echo htmlspecialchars($curriculumOption); ?>"></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                    <div class="help-text">Use school curricula like CBC or IGCSE, or a broader programme track like TVET, University Degree, or Professional Programme.</div>
                                </div>
                                <div class="form-group">
                                    <label for="location">Geographical Area</label>
                                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($profile['location'] ?? ''); ?>" placeholder="e.g. Nairobi, Kisumu, Westlands">
                                </div>
                                <div class="form-group">
                                    <label for="preferred_radius_km">Preferred Tutor Radius (KM)</label>
                                    <input type="number" id="preferred_radius_km" name="preferred_radius_km" min="5" max="500" value="<?php echo htmlspecialchars((string) ($profile['preferred_radius_km'] ?? 50)); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Parent / Guardian Contact</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="guardian_name">Parent / Guardian Name</label>
                                    <input type="text" id="guardian_name" name="guardian_name" value="<?php echo htmlspecialchars($profile['guardian_name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="guardian_phone">Parent / Guardian Phone</label>
                                    <input type="tel" id="guardian_phone" name="guardian_phone" value="<?php echo htmlspecialchars($profile['guardian_phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Learning Preferences</h3>
                            <div class="form-group">
                                <label for="subjects_interested">Subjects Interested In</label>
                                <textarea id="subjects_interested" name="subjects_interested" placeholder="Math, English, Physics, Chemistry, Computer Studies"><?php echo htmlspecialchars($profile['subjects_display'] ?? ($profile['subjects_interested'] ?? '')); ?></textarea>
                                <div class="help-text">Separate subjects with commas so tutors can match you accurately. Suggested subjects: <?php echo htmlspecialchars(implode(', ', array_slice($catalogOptions['subjects'], 0, 10))); ?></div>
                            </div>
                            <div class="form-group">
                                <label for="goals">Learning Goals</label>
                                <textarea id="goals" name="goals" placeholder="What do you want tutoring to help you achieve?"><?php echo htmlspecialchars($profile['goals'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="bio">Additional Notes</label>
                                <textarea id="bio" name="bio" placeholder="Any details that will help tutors support you better"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <button type="submit" class="btn">Save Profile</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
