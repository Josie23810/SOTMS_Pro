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

$studyLevelSuggestionsByEducation = [
    'primary' => ['Grade 4', 'Grade 5', 'Grade 6'],
    'junior_secondary' => ['Grade 7', 'Grade 8', 'Grade 9'],
    'high_school' => ['Form 1', 'Form 2', 'Form 3', 'Form 4'],
    'certificate' => ['Certificate Year 1', 'Certificate Year 2'],
    'diploma' => ['Diploma Year 1', 'Diploma Year 2', 'Diploma Final Year'],
    'bachelors' => ["Bachelor's Year 1", "Bachelor's Year 2", "Bachelor's Year 3", "Bachelor's Year 4"],
    'postgraduate_diploma' => ['Postgraduate Diploma'],
    'masters' => ["Master's Coursework", "Master's Research"],
    'phd' => ['PhD Year 1', 'PhD Candidate'],
    'professional' => ['CPA', 'ACCA', 'Professional Certification'],
    'other' => $studyLevelSuggestions
];

$curriculumSuggestionsByEducation = [
    'primary' => ['CBC', '8-4-4'],
    'junior_secondary' => ['CBC', '8-4-4'],
    'high_school' => ['KCSE', 'IGCSE', 'IB', 'A-Level', '8-4-4'],
    'certificate' => ['TVET', 'Professional Programme'],
    'diploma' => ['TVET', 'Professional Programme'],
    'bachelors' => ['University Degree', 'Professional Programme'],
    'postgraduate_diploma' => ['University Degree', 'Professional Programme'],
    'masters' => ['University Degree'],
    'phd' => ['University Degree'],
    'professional' => ['Professional Programme'],
    'other' => array_values(array_unique(array_merge($catalogOptions['curricula'], ['TVET', 'University Degree', 'Professional Programme'])))
];

$message = '';
$messageType = '';

function normalizeStudentEducationSelection($educationLevel, $levelOfStudy = '', $curriculum = '') {
    $educationLevel = strtolower(trim((string) $educationLevel));
    $levelOfStudy = strtolower(trim((string) $levelOfStudy));
    $curriculum = strtolower(trim((string) $curriculum));

    if ($educationLevel === '') {
        return 'high_school';
    }

    if (isset($GLOBALS['educationLevelOptions'][$educationLevel])) {
        return $educationLevel;
    }

    if (in_array($educationLevel, ['college', 'tertiary', 'university'], true)) {
        if (strpos($levelOfStudy, 'certificate') !== false) {
            return 'certificate';
        }
        if (strpos($levelOfStudy, 'diploma') !== false || strpos($curriculum, 'tvet') !== false) {
            return 'diploma';
        }
        if (strpos($levelOfStudy, 'master') !== false) {
            return 'masters';
        }
        if (strpos($levelOfStudy, 'phd') !== false || strpos($levelOfStudy, 'doctorate') !== false) {
            return 'phd';
        }
        return 'bachelors';
    }

    if (in_array($educationLevel, ['secondary', 'high school', 'senior secondary'], true)) {
        return 'high_school';
    }

    if (in_array($educationLevel, ['junior secondary', 'junior'], true)) {
        return 'junior_secondary';
    }

    return 'other';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $education_level = trim($_POST['education_level'] ?? 'high_school');
    $level_of_study = trim($_POST['level_of_study'] ?? '');
    if ($level_of_study === '__custom__') {
        $level_of_study = trim($_POST['level_of_study_custom'] ?? '');
    }
    $current_institution = trim($_POST['current_institution'] ?? '');
    $curriculum = trim($_POST['curriculum'] ?? '');
    if ($curriculum === '__custom__') {
        $curriculum = trim($_POST['curriculum_custom'] ?? '');
    }
    $location = trim($_POST['location'] ?? '');
    if ($location === '__custom__') {
        $location = trim($_POST['location_custom'] ?? '');
    }
    $guardian_name = trim($_POST['guardian_name'] ?? '');
    $guardian_phone = trim($_POST['guardian_phone'] ?? '');
    $subject_select = isset($_POST['subjects_interested_select']) && is_array($_POST['subjects_interested_select'])
        ? array_values(array_filter(array_map('trim', $_POST['subjects_interested_select']), static function ($value) {
            return $value !== '';
        }))
        : [];
    $custom_subjects_interested = trim($_POST['custom_subjects_interested'] ?? '');
    if ($custom_subjects_interested !== '') {
        $subject_select[] = $custom_subjects_interested;
    }
    $subjects_interested = implode(', ', array_values(array_unique($subject_select)));
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
$selectedEducation = normalizeStudentEducationSelection(
    $profile['education_level'] ?? 'high_school',
    $profile['level_of_study'] ?? '',
    $profile['curriculum'] ?? ''
);
$initialStudyLevels = $studyLevelSuggestionsByEducation[$selectedEducation] ?? $studyLevelSuggestionsByEducation['other'];
$initialCurricula = $curriculumSuggestionsByEducation[$selectedEducation] ?? $curriculumSuggestionsByEducation['other'];
$currentStudyLevel = $profile['level_of_study'] ?? '';
$currentCurriculum = $profile['curriculum'] ?? '';
$studyLevelIsPreset = in_array($currentStudyLevel, $initialStudyLevels, true);
$curriculumIsPreset = in_array($currentCurriculum, $initialCurricula, true);
$locationOptions = $catalogOptions['service_areas'];
$currentLocation = $profile['location'] ?? '';
$locationIsPreset = in_array($currentLocation, $locationOptions, true);
$savedSubjectValues = !empty($profile['subject_names'])
    ? $profile['subject_names']
    : normalizeCsvArray($profile['subjects_display'] ?? ($profile['subjects_interested'] ?? ''));
$subjectOptionValues = $catalogOptions['subjects'];
$selectedSubjectOptions = array_values(array_intersect($savedSubjectValues, $subjectOptionValues));
$customSubjectValues = array_values(array_diff($savedSubjectValues, $subjectOptionValues));
$customSubjectsValue = implode(', ', $customSubjectValues);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student / Parent Profile - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-shell {
            max-width: 1140px;
            margin: 0 auto;
        }
        .profile-content {
            padding: 24px;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
        }
        .profile-image-section,
        .profile-form {
            background: linear-gradient(180deg, #ffffff, #f8fbff);
            border-radius: 18px;
            padding: 22px;
            border: 1px solid rgba(37, 99, 235, 0.12);
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.06);
        }
        .current-image {
            width: 200px;
            height: 200px;
            border-radius: 32px;
            object-fit: cover;
            border: 4px solid #dbeafe;
            margin-bottom: 20px;
        }
        .form-section {
            margin-bottom: 28px;
            padding-bottom: 18px;
            border-bottom: 1px solid rgba(236, 72, 153, 0.12);
        }
        .form-section:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .form-section h3 {
            margin: 0 0 16px;
            color: #1f2937;
            font-size: 1.12rem;
            font-family: 'Poppins', sans-serif;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
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
        .guided-row {
            display: grid;
            gap: 10px;
        }
        .guided-checklist {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 14px;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            background: #ffffff;
        }
        .guided-option {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            font-weight: 500;
            color: #334155;
        }
        .guided-option input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        .guided-note {
            color: #64748b;
            font-size: 0.82rem;
        }
        .suggestion-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .suggestion-chip {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
        }
        .suggestion-chip:hover {
            background: #dbeafe;
        }
        .custom-field {
            display: none;
            margin-top: 10px;
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
            .guided-checklist {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="form-page">
    <div class="form-shell profile-shell">
        <div class="form-hero">
            <h1>Student / Parent Profile</h1>
            <p>Update your details for better tutor matches.</p>
        </div>

        <div class="form-nav">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="find_tutors.php">Find Tutors</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>

        <div class="form-content profile-content">
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
                            <h3>Learner</h3>
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
                                    <div class="help-text">Choose the stage first.</div>
                                </div>
                                <div class="form-group">
                                    <label for="level_of_study">Study Level</label>
                                    <select id="level_of_study" name="level_of_study">
                                        <option value="">Select level</option>
                                        <?php foreach ($initialStudyLevels as $studyLevelOption): ?>
                                            <option value="<?php echo htmlspecialchars($studyLevelOption); ?>" <?php echo $currentStudyLevel === $studyLevelOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($studyLevelOption); ?></option>
                                        <?php endforeach; ?>
                                        <option value="__custom__" <?php echo !$studyLevelIsPreset && $currentStudyLevel !== '' ? 'selected' : ''; ?>>Other / Custom</option>
                                    </select>
                                    <input type="text" id="level_of_study_custom" name="level_of_study_custom" class="custom-field" value="<?php echo htmlspecialchars($currentStudyLevel); ?>" placeholder="Other level" <?php echo !$studyLevelIsPreset && $currentStudyLevel !== '' ? 'style="display:block;"' : ''; ?>>
                                    <input type="hidden" id="initial_level_of_study" value="<?php echo htmlspecialchars($currentStudyLevel); ?>">
                                    <div class="help-text" id="study-level-help">Pick the exact class or year.</div>
                                    <div class="suggestion-list" id="study-level-suggestions"></div>
                                </div>
                                <div class="form-group">
                                    <label for="current_institution">School / Institution</label>
                                    <input type="text" id="current_institution" name="current_institution" value="<?php echo htmlspecialchars($profile['current_institution'] ?? ''); ?>" placeholder="e.g. Alliance High School">
                                </div>
                                <div class="form-group">
                                    <label for="curriculum">Curriculum</label>
                                    <select id="curriculum" name="curriculum">
                                        <option value="">Select curriculum</option>
                                        <?php foreach ($initialCurricula as $curriculumOption): ?>
                                            <option value="<?php echo htmlspecialchars($curriculumOption); ?>" <?php echo $currentCurriculum === $curriculumOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($curriculumOption); ?></option>
                                        <?php endforeach; ?>
                                        <option value="__custom__" <?php echo !$curriculumIsPreset && $currentCurriculum !== '' ? 'selected' : ''; ?>>Other / Custom</option>
                                    </select>
                                    <input type="text" id="curriculum_custom" name="curriculum_custom" class="custom-field" value="<?php echo htmlspecialchars($currentCurriculum); ?>" placeholder="Other curriculum" <?php echo !$curriculumIsPreset && $currentCurriculum !== '' ? 'style="display:block;"' : ''; ?>>
                                    <input type="hidden" id="initial_curriculum" value="<?php echo htmlspecialchars($currentCurriculum); ?>">
                                    <div class="help-text" id="curriculum-help">Pick the main curriculum or track.</div>
                                    <div class="suggestion-list" id="curriculum-suggestions"></div>
                                </div>
                                <div class="form-group">
                                    <label for="location">Location</label>
                                    <select id="location" name="location">
                                        <option value="">Select area</option>
                                        <?php foreach ($locationOptions as $locationOption): ?>
                                            <option value="<?php echo htmlspecialchars($locationOption); ?>" <?php echo $currentLocation === $locationOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($locationOption); ?></option>
                                        <?php endforeach; ?>
                                        <option value="__custom__" <?php echo !$locationIsPreset && $currentLocation !== '' ? 'selected' : ''; ?>>Other / Custom</option>
                                    </select>
                                    <input type="text" id="location_custom" name="location_custom" class="custom-field" value="<?php echo htmlspecialchars($currentLocation); ?>" placeholder="Other location" <?php echo !$locationIsPreset && $currentLocation !== '' ? 'style="display:block;"' : ''; ?>>
                                    <input type="hidden" id="initial_location" value="<?php echo htmlspecialchars($currentLocation); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="preferred_radius_km">Tutor Radius (KM)</label>
                                    <input type="number" id="preferred_radius_km" name="preferred_radius_km" min="5" max="500" value="<?php echo htmlspecialchars((string) ($profile['preferred_radius_km'] ?? 50)); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Guardian</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="guardian_name">Guardian Name</label>
                                    <input type="text" id="guardian_name" name="guardian_name" value="<?php echo htmlspecialchars($profile['guardian_name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="guardian_phone">Guardian Phone</label>
                                    <input type="tel" id="guardian_phone" name="guardian_phone" value="<?php echo htmlspecialchars($profile['guardian_phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Learning</h3>
                            <div class="form-group">
                                <label for="subjects_interested_select">Subjects</label>
                                <div class="guided-row">
                                    <div id="subjects_interested_select" class="guided-checklist">
                                        <?php foreach ($catalogOptions['subjects'] as $subjectOption): ?>
                                            <label class="guided-option">
                                                <input
                                                    type="checkbox"
                                                    name="subjects_interested_select[]"
                                                    value="<?php echo htmlspecialchars($subjectOption); ?>"
                                                    <?php echo in_array($subjectOption, $selectedSubjectOptions, true) ? 'checked' : ''; ?>
                                                >
                                                <?php echo htmlspecialchars($subjectOption); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <input
                                        type="text"
                                        id="custom_subjects_interested"
                                        name="custom_subjects_interested"
                                        value="<?php echo htmlspecialchars($customSubjectsValue); ?>"
                                        placeholder="Other subject"
                                    >
                                    <div class="guided-note">Select all that apply.</div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="goals">Goals</label>
                                <textarea id="goals" name="goals" placeholder="What should tutoring help with?"><?php echo htmlspecialchars($profile['goals'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="bio">Notes</label>
                                <textarea id="bio" name="bio" placeholder="Any helpful details"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <button type="submit" class="btn">Save Profile</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
        const studyLevelSuggestionsByEducation = <?php echo json_encode($studyLevelSuggestionsByEducation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const curriculumSuggestionsByEducation = <?php echo json_encode($curriculumSuggestionsByEducation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

        const educationLevelField = document.getElementById('education_level');
        const studyLevelField = document.getElementById('level_of_study');
        const studyLevelCustomField = document.getElementById('level_of_study_custom');
        const initialStudyLevelField = document.getElementById('initial_level_of_study');
        const studyLevelHelp = document.getElementById('study-level-help');
        const studyLevelSuggestions = document.getElementById('study-level-suggestions');
        const curriculumField = document.getElementById('curriculum');
        const curriculumCustomField = document.getElementById('curriculum_custom');
        const initialCurriculumField = document.getElementById('initial_curriculum');
        const curriculumHelp = document.getElementById('curriculum-help');
        const curriculumSuggestions = document.getElementById('curriculum-suggestions');
        const locationField = document.getElementById('location');
        const locationCustomField = document.getElementById('location_custom');
        const initialLocationField = document.getElementById('initial_location');

        function getActiveValue(selectField, customField, initialField) {
            if (selectField.value && selectField.value !== '__custom__') {
                return selectField.value;
            }

            if (customField.value.trim() !== '') {
                return customField.value.trim();
            }

            return initialField.value.trim();
        }

        function fillSelectOptions(selectField, values, currentValue, placeholder) {
            selectField.innerHTML = '';

            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = placeholder;
            selectField.appendChild(placeholderOption);

            const uniqueValues = [...new Set(values.filter((value) => value && value.trim() !== ''))];
            uniqueValues.forEach((value) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                selectField.appendChild(option);
            });

            const customOption = document.createElement('option');
            customOption.value = '__custom__';
            customOption.textContent = 'Other / Custom';
            selectField.appendChild(customOption);

            if (currentValue && uniqueValues.includes(currentValue)) {
                selectField.value = currentValue;
            } else if (currentValue) {
                selectField.value = '__custom__';
            } else {
                selectField.value = '';
            }
        }

        function toggleCustomField(selectField, customField, currentValue) {
            if (selectField.value === '__custom__') {
                customField.style.display = 'block';
                if (!customField.value.trim() && currentValue) {
                    customField.value = currentValue;
                }
            } else {
                customField.style.display = 'none';
            }
        }

        function fillSuggestionList(containerEl, values, selectField, customField) {
            containerEl.innerHTML = '';
            values.forEach((value) => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'suggestion-chip';
                chip.textContent = value;
                chip.addEventListener('click', () => {
                    selectField.value = value;
                    customField.style.display = 'none';
                    selectField.focus();
                });
                containerEl.appendChild(chip);
            });
        }

        function updateEducationGuidance() {
            const selectedEducation = educationLevelField.value || 'other';
            const studyLevels = studyLevelSuggestionsByEducation[selectedEducation] || studyLevelSuggestionsByEducation.other;
            const curricula = curriculumSuggestionsByEducation[selectedEducation] || curriculumSuggestionsByEducation.other;
            const currentStudyLevel = getActiveValue(studyLevelField, studyLevelCustomField, initialStudyLevelField);
            const currentCurriculum = getActiveValue(curriculumField, curriculumCustomField, initialCurriculumField);

            fillSelectOptions(studyLevelField, studyLevels, currentStudyLevel, 'Select the exact class or study level');
            fillSelectOptions(curriculumField, curricula, currentCurriculum, 'Select the curriculum or programme track');
            fillSuggestionList(studyLevelSuggestions, studyLevels, studyLevelField, studyLevelCustomField);
            fillSuggestionList(curriculumSuggestions, curricula, curriculumField, curriculumCustomField);
            toggleCustomField(studyLevelField, studyLevelCustomField, currentStudyLevel);
            toggleCustomField(curriculumField, curriculumCustomField, currentCurriculum);

            if (studyLevels.length > 0) {
                studyLevelHelp.textContent = 'Suggested: ' + studyLevels.join(', ') + '.';
            } else {
                studyLevelHelp.textContent = 'Enter the exact class or year.';
            }

            if (curricula.length > 0) {
                curriculumHelp.textContent = 'Suggested: ' + curricula.join(', ') + '.';
            } else {
                curriculumHelp.textContent = 'Enter the curriculum or track.';
            }
        }

        educationLevelField.addEventListener('change', updateEducationGuidance);
        studyLevelField.addEventListener('change', () => toggleCustomField(studyLevelField, studyLevelCustomField, initialStudyLevelField.value.trim()));
        curriculumField.addEventListener('change', () => toggleCustomField(curriculumField, curriculumCustomField, initialCurriculumField.value.trim()));
        locationField.addEventListener('change', () => toggleCustomField(locationField, locationCustomField, initialLocationField.value.trim()));
        updateEducationGuidance();
        toggleCustomField(locationField, locationCustomField, initialLocationField.value.trim());
    </script>
</body>
</html>
