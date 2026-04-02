<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
checkAccess(['student']);

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $education_level = $_POST['education_level'] ?? 'high_school';
    $current_institution = trim($_POST['current_institution'] ?? '');
    $subjects_interested = trim($_POST['subjects_interested'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $goals = trim($_POST['goals'] ?? '');

    // Handle image upload
    $profile_image = '';
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/profiles/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                $profile_image = 'uploads/profiles/' . $new_filename;
            } else {
                $message = "Failed to upload image.";
                $messageType = 'error';
            }
        } else {
            $message = "Invalid image format. Please use JPG, PNG, or GIF.";
            $messageType = 'error';
        }
    }

    if (empty($message)) {
        try {
            // Check if profile exists
            $stmt = $pdo->prepare('SELECT id FROM student_profiles WHERE user_id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing profile
                $sql = 'UPDATE student_profiles SET full_name = ?, phone = ?, education_level = ?, current_institution = ?, subjects_interested = ?, bio = ?, goals = ?';
                $params = [$full_name, $phone, $education_level, $current_institution, $subjects_interested, $bio, $goals];

                if (!empty($profile_image)) {
                    $sql .= ', profile_image = ?';
                    $params[] = $profile_image;
                }

                $sql .= ' WHERE user_id = ?';
                $params[] = $_SESSION['user_id'];

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                // Insert new profile
                $stmt = $pdo->prepare('INSERT INTO student_profiles (user_id, profile_image, full_name, phone, education_level, current_institution, subjects_interested, bio, goals) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$_SESSION['user_id'], $profile_image, $full_name, $phone, $education_level, $current_institution, $subjects_interested, $bio, $goals]);
            }

            $message = "Profile updated successfully!";
            $messageType = 'success';
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            $message = "Error updating profile: " . $e->getMessage();
            $messageType = 'error';
            error_log('Profile update error: ' . $e->getMessage());
        }
    }
}

// Get current profile data
$profile = null;
try {
    $stmt = $pdo->prepare('SELECT * FROM student_profiles WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Profile fetch error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - SOTMS PRO</title>
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
            max-width: 1000px;
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
        .profile-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }
        .profile-image-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
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
        .profile-form {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        .form-section {
            margin-bottom: 30px;
        }
        .form-section h3 {
            margin: 0 0 15px;
            color: #1f2937;
            font-size: 1.2rem;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #374151;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .btn {
            background: #2563eb;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .subjects-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .subject-tag {
            background: #e0f2fe;
            color: #2563eb;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
        }
        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            .current-image {
                width: 150px;
                height: 150px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Profile</h1>
            <p>Manage your personal information and preferences</p>
        </div>

        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
            <a href="../config/auth/logout.php">Logout</a>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="profile-grid">
                    <div class="profile-image-section">
                        <h3>Profile Picture</h3>
                        <?php if ($profile && $profile['profile_image']): ?>
                            <img src="../<?php echo htmlspecialchars($profile['profile_image']); ?>" alt="Profile Image" class="current-image">
                        <?php else: ?>
                            <div class="current-image" style="background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #6b7280; font-size: 48px;">
                                👤
                            </div>
                        <?php endif; ?>
                        <div class="form-group" style="margin-top: 20px;">
                            <label for="profile_image">Upload New Image</label>
                            <input type="file" id="profile_image" name="profile_image" accept="image/*">
                            <small style="color: #6b7280; display: block; margin-top: 5px;">JPG, PNG, or GIF. Max 5MB.</small>
                        </div>
                    </div>

                    <div class="profile-form">
                        <div class="form-section">
                            <h3>Personal Information</h3>
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Education</h3>
                            <div class="form-group">
                                <label for="education_level">Education Level</label>
                                <select id="education_level" name="education_level">
                                    <option value="high_school" <?php echo ($profile['education_level'] ?? '') === 'high_school' ? 'selected' : ''; ?>>High School</option>
                                    <option value="college" <?php echo ($profile['education_level'] ?? '') === 'college' ? 'selected' : ''; ?>>College</option>
                                    <option value="university" <?php echo ($profile['education_level'] ?? '') === 'university' ? 'selected' : ''; ?>>University</option>
                                    <option value="masters" <?php echo ($profile['education_level'] ?? '') === 'masters' ? 'selected' : ''; ?>>Master's Degree</option>
                                    <option value="phd" <?php echo ($profile['education_level'] ?? '') === 'phd' ? 'selected' : ''; ?>>PhD</option>
                                    <option value="other" <?php echo ($profile['education_level'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="current_institution">Current Institution/School</label>
                                <input type="text" id="current_institution" name="current_institution" value="<?php echo htmlspecialchars($profile['current_institution'] ?? ''); ?>" placeholder="e.g., Harvard University, Local High School">
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Academic Interests</h3>
                            <div class="form-group">
                                <label for="subjects_interested">Subjects Interested In</label>
                                <textarea id="subjects_interested" name="subjects_interested" placeholder="List subjects you're interested in learning (e.g., Mathematics, Physics, Chemistry, English, Computer Science)"><?php echo htmlspecialchars($profile['subjects_interested'] ?? ''); ?></textarea>
                            </div>
                            <?php if ($profile && $profile['subjects_interested']): ?>
                                <div class="subjects-tags">
                                    <?php
                                    $subjects = explode(',', $profile['subjects_interested']);
                                    foreach ($subjects as $subject) {
                                        $subject = trim($subject);
                                        if (!empty($subject)) {
                                            echo '<span class="subject-tag">' . htmlspecialchars($subject) . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-section">
                            <h3>About You</h3>
                            <div class="form-group">
                                <label for="bio">Bio</label>
                                <textarea id="bio" name="bio" placeholder="Tell us about yourself, your background, and what you're passionate about..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="goals">Learning Goals</label>
                                <textarea id="goals" name="goals" placeholder="What are your academic goals? What do you hope to achieve through tutoring?"><?php echo htmlspecialchars($profile['goals'] ?? ''); ?></textarea>
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