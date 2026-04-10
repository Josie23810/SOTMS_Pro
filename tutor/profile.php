<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
require_once '../includes/upload_helpers.php';
require_once '../includes/services/ProfileTaxonomyService.php';
require_once '../includes/services/TutorVerificationService.php';
checkAccess(['tutor']);

ensurePlatformStructures($pdo);
$catalogOptions = ProfileTaxonomyService::getCatalogOptions($pdo);
getTutorId($pdo, $_SESSION['user_id']);

$message = '';
$messageType = '';

$userStmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
$userStmt->execute([$_SESSION['user_id']]);
$account = $userStmt->fetch();
$accountEmail = $account['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $existingProfile = fetchTutorProfile($pdo, $_SESSION['user_id']);
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? $accountEmail);
    $id_number = trim($_POST['id_number'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $subjects_taught = trim($_POST['subjects_taught'] ?? '');
    $curriculum_specialties = trim($_POST['curriculum_specialties'] ?? '');
    $study_levels_supported = trim($_POST['study_levels_supported'] ?? '');
    $qualifications = trim($_POST['qualifications'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $hourly_rate = trim($_POST['hourly_rate'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $service_areas = trim($_POST['service_areas'] ?? '');
    $availability_days = isset($_POST['availability_days']) && is_array($_POST['availability_days']) ? implode(',', $_POST['availability_days']) : '';
    $availability_start = trim($_POST['availability_start'] ?? '');
    $availability_end = trim($_POST['availability_end'] ?? '');
    $delivery_mode = trim($_POST['delivery_mode'] ?? 'both');
    $location_note = trim($_POST['location_note'] ?? '');
    $max_sessions_per_day = max(1, intval($_POST['max_sessions_per_day'] ?? 1));
    $verification_status = TutorVerificationService::determineStatusForProfileUpdate($existingProfile, [
        'full_name' => $full_name,
        'email' => $email,
        'id_number' => $id_number,
        'qualifications' => $qualifications
    ], false);

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

    $qualification_document = '';
    if (empty($message)) {
        try {
            $upload = saveUploadedFile(
                'qualification_document',
                dirname(__DIR__) . '/uploads/tutor_qualifications',
                'uploads/tutor_qualifications',
                ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
                [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'image/jpeg',
                    'image/png'
                ],
                'qualification_' . $_SESSION['user_id'],
                8 * 1024 * 1024
            );
            if ($upload) {
                $qualification_document = $upload['path'];
                $verification_status = TutorVerificationService::determineStatusForProfileUpdate($existingProfile, [
                    'full_name' => $full_name,
                    'email' => $email,
                    'id_number' => $id_number,
                    'qualifications' => $qualifications
                ], true);
            }
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }

    if (empty($message)) {
        try {
            $stmt = $pdo->prepare('SELECT id FROM tutor_profiles WHERE user_id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $existing = $stmt->fetch();

            if ($existing) {
                $sql = 'UPDATE tutor_profiles
                        SET full_name = ?, phone = ?, email = ?, id_number = ?, age = ?, subjects_taught = ?, curriculum_specialties = ?, study_levels_supported = ?, qualifications = ?, bio = ?, experience = ?, hourly_rate = ?, location = ?, service_areas = ?, availability_days = ?, availability_start = ?, availability_end = ?, max_sessions_per_day = ?, verification_status = ?';
                $params = [
                    $full_name,
                    $phone,
                    $email,
                    $id_number,
                    $age ?: null,
                    $subjects_taught,
                    $curriculum_specialties,
                    $study_levels_supported,
                    $qualifications,
                    $bio,
                    $experience,
                    $hourly_rate,
                    $location,
                    $service_areas,
                    $availability_days,
                    $availability_start,
                    $availability_end,
                    $max_sessions_per_day,
                    $verification_status
                ];

                if (!empty($profile_image)) {
                    $sql .= ', profile_image = ?';
                    $params[] = $profile_image;
                }

                if (!empty($qualification_document)) {
                    $sql .= ', qualification_document = ?';
                    $params[] = $qualification_document;
                }

                $sql .= ' WHERE user_id = ?';
                $params[] = $_SESSION['user_id'];

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO tutor_profiles (
                        user_id, profile_image, full_name, phone, email, id_number, age, subjects_taught,
                        curriculum_specialties, study_levels_supported, qualifications, qualification_document,
                        bio, experience, hourly_rate, location, service_areas, availability_days,
                        availability_start, availability_end, max_sessions_per_day, verification_status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $_SESSION['user_id'],
                    $profile_image,
                    $full_name,
                    $phone,
                    $email,
                    $id_number,
                    $age ?: null,
                    $subjects_taught,
                    $curriculum_specialties,
                    $study_levels_supported,
                    $qualifications,
                    $qualification_document,
                    $bio,
                    $experience,
                    $hourly_rate,
                    $location,
                    $service_areas,
                    $availability_days,
                    $availability_start,
                    $availability_end,
                    $max_sessions_per_day,
                    $verification_status
                ]);
            }

            ProfileTaxonomyService::syncTutorProfile($pdo, $_SESSION['user_id'], [
                'subjects_taught' => $subjects_taught,
                'curriculum_specialties' => $curriculum_specialties,
                'study_levels_supported' => $study_levels_supported,
                'service_areas' => $service_areas,
                'availability_days' => isset($_POST['availability_days']) && is_array($_POST['availability_days']) ? $_POST['availability_days'] : [],
                'availability_start' => $availability_start,
                'availability_end' => $availability_end,
                'delivery_mode' => $delivery_mode,
                'location_note' => $location_note
            ]);

            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            $message = 'Error updating profile. Please try again.';
            $messageType = 'error';
            error_log('Tutor profile save error: ' . $e->getMessage());
        }
    }
}

$profile = fetchTutorProfile($pdo, $_SESSION['user_id']);
$savedDays = !empty($profile['availability_days']) ? array_map('trim', explode(',', $profile['availability_days'])) : [];
$availabilitySlots = $profile['availability_slots'] ?? [];
$primarySlot = $availabilitySlots[0] ?? [];
$defaultDeliveryMode = $primarySlot['delivery_mode'] ?? 'both';
$slotLocationNote = $primarySlot['location_note'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutor Profile - SOTMS PRO</title>
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
            max-width: 1120px;
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
        .nav { background: #f8fafc; padding: 20px; border-bottom: 1px solid #e2e8f0; text-align: center; }
        .nav a { color: #2563eb; text-decoration: none; margin: 0 12px; font-weight: 600; padding: 10px 14px; border-radius: 8px; }
        .nav a:hover { background: #e0f2fe; }
        .content { padding: 30px; }
        .profile-grid { display: grid; grid-template-columns: 300px 1fr; gap: 24px; }
        .profile-image-section, .profile-form {
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
        .form-section { margin-bottom: 28px; }
        .form-section h3 { margin: 0 0 16px; font-size: 1.18rem; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #374151; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 15px; box-sizing: border-box; background: white; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .btn { background: #2563eb; color: white; padding: 12px 24px; border: none; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; }
        .btn:hover { background: #1d4ed8; }
        .message { padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .status-chip {
            display: inline-flex;
            padding: 8px 12px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: 700;
            font-size: 0.84rem;
        }
        .help-text { color: #64748b; font-size: 0.9rem; margin-top: 6px; }
        @media (max-width: 768px) {
            .profile-grid, .form-grid { grid-template-columns: 1fr; }
            .current-image { width: 160px; height: 160px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Tutor Profile</h1>
            <p>Complete your tutor profile with your ID, age, email, qualifications, curriculum focus, and service area so students can find you.</p>
        </div>
        <div class="nav">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="schedule.php">Manage Schedule</a>
            <a href="upload_materials.php">Upload Materials</a>
            <a href="settings.php">Settings</a>
        </div>
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div style="margin-bottom:20px;">
                <span class="status-chip">Verification Status: <?php echo htmlspecialchars(ucfirst($profile['verification_status'] ?? 'submitted')); ?></span>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="profile-grid">
                    <div class="profile-image-section">
                        <h3>Profile Picture</h3>
                        <?php if ($profile && !empty($profile['profile_image'])): ?>
                            <img src="../<?php echo htmlspecialchars($profile['profile_image']); ?>" alt="Profile Image" class="current-image">
                        <?php else: ?>
                            <div class="current-image" style="background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-size:48px; color:#6b7280;">
                                <?php echo htmlspecialchars(strtoupper(substr($_SESSION['name'], 0, 1))); ?>
                            </div>
                        <?php endif; ?>
                        <div class="form-group" style="margin-top:24px;">
                            <label for="profile_image">Upload New Image</label>
                            <input type="file" id="profile_image" name="profile_image" accept="image/*">
                        </div>
                    </div>

                    <div class="profile-form">
                        <div class="form-section">
                            <h3>Personal Information</h3>
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
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? $accountEmail); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="id_number">ID Number</label>
                                    <input type="text" id="id_number" name="id_number" value="<?php echo htmlspecialchars($profile['id_number'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="age">Age</label>
                                    <input type="number" id="age" name="age" min="18" max="100" value="<?php echo htmlspecialchars((string) ($profile['age'] ?? '')); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="location">Primary Location</label>
                                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($profile['location'] ?? ''); ?>" placeholder="e.g. Nairobi, Kisumu, Eldoret">
                                </div>
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label for="service_areas">Geographical Areas You Serve</label>
                                    <input type="text" id="service_areas" name="service_areas" value="<?php echo htmlspecialchars($profile['service_areas_display'] ?? ($profile['service_areas'] ?? '')); ?>" placeholder="Westlands, Kilimani, Online, Nakuru">
                                    <div class="help-text">Suggested service areas: <?php echo htmlspecialchars(implode(', ', $catalogOptions['service_areas'])); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Teaching Profile</h3>
                            <div class="form-group">
                                <label for="subjects_taught">Subjects Taught</label>
                                <textarea id="subjects_taught" name="subjects_taught" placeholder="Math, Physics, Chemistry, English"><?php echo htmlspecialchars($profile['subjects_taught_display'] ?? ($profile['subjects_taught'] ?? '')); ?></textarea>
                                <div class="help-text">Suggested subjects: <?php echo htmlspecialchars(implode(', ', array_slice($catalogOptions['subjects'], 0, 10))); ?></div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="curriculum_specialties">Curriculum Specialties</label>
                                    <input type="text" id="curriculum_specialties" name="curriculum_specialties" value="<?php echo htmlspecialchars($profile['curriculum_specialties_display'] ?? ($profile['curriculum_specialties'] ?? '')); ?>" placeholder="CBC, 8-4-4, IGCSE, IB">
                                    <div class="help-text">Suggested curricula: <?php echo htmlspecialchars(implode(', ', $catalogOptions['curricula'])); ?></div>
                                </div>
                                <div class="form-group">
                                    <label for="study_levels_supported">Study Levels Supported</label>
                                    <input type="text" id="study_levels_supported" name="study_levels_supported" value="<?php echo htmlspecialchars($profile['study_levels_supported_display'] ?? ($profile['study_levels_supported'] ?? '')); ?>" placeholder="Grade 8, Form 4, University Year 1">
                                    <div class="help-text">Suggested levels: <?php echo htmlspecialchars(implode(', ', array_slice($catalogOptions['study_levels'], 0, 8))); ?></div>
                                </div>
                                <div class="form-group">
                                    <label for="hourly_rate">Session Rate (KSh)</label>
                                    <input type="text" id="hourly_rate" name="hourly_rate" value="<?php echo htmlspecialchars($profile['hourly_rate'] ?? ''); ?>" placeholder="e.g. 1500">
                                </div>
                                <div class="form-group">
                                    <label for="max_sessions_per_day">Maximum Sessions Per Day</label>
                                    <input type="number" id="max_sessions_per_day" name="max_sessions_per_day" min="1" max="20" value="<?php echo htmlspecialchars((string) ($profile['max_sessions_per_day'] ?? 4)); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Availability</h3>
                            <?php if (!empty($profile['availability_summary'])): ?>
                                <div class="help-text" style="margin-bottom:12px;">Saved availability slots: <?php echo htmlspecialchars($profile['availability_summary']); ?></div>
                            <?php endif; ?>
                            <div class="form-group">
                                <label>Available Days</label>
                                <div class="form-grid">
                                    <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
                                        <label style="display:flex; gap:8px; align-items:center; margin:0; font-weight:500;">
                                            <input type="checkbox" name="availability_days[]" value="<?php echo $day; ?>" <?php echo in_array($day, $savedDays, true) ? 'checked' : ''; ?> style="width:auto;">
                                            <?php echo $day; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="availability_start">Available From</label>
                                    <input type="time" id="availability_start" name="availability_start" value="<?php echo htmlspecialchars($profile['availability_start'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="availability_end">Available To</label>
                                    <input type="time" id="availability_end" name="availability_end" value="<?php echo htmlspecialchars($profile['availability_end'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="delivery_mode">Delivery Mode</label>
                                    <select id="delivery_mode" name="delivery_mode" style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:10px; font-size:15px; box-sizing:border-box; background:white;">
                                        <option value="both" <?php echo $defaultDeliveryMode === 'both' ? 'selected' : ''; ?>>Online and In-person</option>
                                        <option value="online" <?php echo $defaultDeliveryMode === 'online' ? 'selected' : ''; ?>>Online Only</option>
                                        <option value="in_person" <?php echo $defaultDeliveryMode === 'in_person' ? 'selected' : ''; ?>>In-person Only</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="location_note">Availability Note</label>
                                    <input type="text" id="location_note" name="location_note" value="<?php echo htmlspecialchars($slotLocationNote); ?>" placeholder="e.g. Online via Google Meet, Westlands only">
                                </div>
                            </div>
                            <div class="help-text">Each selected day is now stored as its own availability slot to make booking checks more accurate.</div>
                        </div>

                        <div class="form-section">
                            <h3>Qualifications & Experience</h3>
                            <div class="form-group">
                                <label for="qualifications">Qualifications</label>
                                <textarea id="qualifications" name="qualifications" placeholder="Degrees, certifications, licenses, and teaching credentials"><?php echo htmlspecialchars($profile['qualifications'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="qualification_document">Qualification / ID Document Upload</label>
                                <input type="file" id="qualification_document" name="qualification_document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <div class="help-text">Upload a certificate, transcript, or identification document for admin verification.</div>
                                <?php if (!empty($profile['qualification_document'])): ?>
                                    <p style="margin-top:10px;"><a href="../<?php echo htmlspecialchars($profile['qualification_document']); ?>" target="_blank" class="btn">View Current Document</a></p>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="experience">Experience</label>
                                <textarea id="experience" name="experience" placeholder="Years of teaching, specialties, coaching history"><?php echo htmlspecialchars($profile['experience'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="bio">About You</label>
                                <textarea id="bio" name="bio" placeholder="Describe your teaching style, strengths, and what students can expect"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
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
