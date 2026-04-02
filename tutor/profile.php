<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
checkAccess(['tutor']);

$message = '';
$messageType = '';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tutor_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        profile_image VARCHAR(255),
        full_name VARCHAR(100),
        phone VARCHAR(20),
        subjects_taught TEXT,
        qualifications TEXT,
        bio TEXT,
        experience TEXT,
        hourly_rate VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    error_log('Tutor profile table creation error: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subjects_taught = trim($_POST['subjects_taught'] ?? '');
    $qualifications = trim($_POST['qualifications'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $hourly_rate = trim($_POST['hourly_rate'] ?? '');

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
                $message = 'Failed to upload image.';
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid image format. Please use JPG, PNG, or GIF.';
            $messageType = 'error';
        }
    }

    if (empty($message)) {
        try {
            $stmt = $pdo->prepare('SELECT id FROM tutor_profiles WHERE user_id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $existing = $stmt->fetch();

            if ($existing) {
                $sql = 'UPDATE tutor_profiles SET full_name = ?, phone = ?, subjects_taught = ?, qualifications = ?, bio = ?, experience = ?, hourly_rate = ?';
                $params = [$full_name, $phone, $subjects_taught, $qualifications, $bio, $experience, $hourly_rate];

                if (!empty($profile_image)) {
                    $sql .= ', profile_image = ?';
                    $params[] = $profile_image;
                }

                $sql .= ' WHERE user_id = ?';
                $params[] = $_SESSION['user_id'];

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                $stmt = $pdo->prepare('INSERT INTO tutor_profiles (user_id, profile_image, full_name, phone, subjects_taught, qualifications, bio, experience, hourly_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$_SESSION['user_id'], $profile_image, $full_name, $phone, $subjects_taught, $qualifications, $bio, $experience, $hourly_rate]);
            }

            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            $message = 'Error updating profile. Please try again.';
            $messageType = 'error';
            error_log('Tutor profile save error: ' . $e->getMessage());
        }
    }
}

$profile = null;
try {
    $stmt = $pdo->prepare('SELECT * FROM tutor_profiles WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Tutor profile fetch error: ' . $e->getMessage());
}
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
        .header h1 { margin: 0; font-size: 2.5rem; }
        .header p { margin: 10px 0 0; opacity: 0.9; }
        .nav { background: #f8fafc; padding: 20px; border-bottom: 1px solid #e2e8f0; text-align: center; }
        .nav a { color: #2563eb; text-decoration: none; margin: 0 12px; font-weight: 600; padding: 10px 14px; border-radius: 8px; transition: background 0.2s; }
        .nav a:hover { background: #e0f2fe; }
        .content { padding: 30px; }
        .profile-grid { display: grid; grid-template-columns: 300px 1fr; gap: 30px; }
        .profile-image-section { background: #f8fafc; border-radius: 12px; padding: 22px; text-align: center; border: 1px solid #e2e8f0; }
        .current-image { width: 200px; height: 200px; border-radius: 50%; object-fit: cover; border: 4px solid #e2e8f0; margin-bottom: 20px; }
        .profile-form { background: #f8fafc; border-radius: 12px; padding: 22px; border: 1px solid #e2e8f0; }
        .form-section { margin-bottom: 28px; }
        .form-section h3 { margin: 0 0 18px; color: #1f2937; font-size: 1.2rem; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #374151; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 13px 14px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 1rem; background: white; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .btn { background: #2563eb; color: white; padding: 12px 24px; border: none; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #1d4ed8; }
        .message { padding: 16px; border-radius: 12px; margin-bottom: 24px; }
        .success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .tag-list { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        .tag-item { background: #e0f2fe; color: #2563eb; padding: 8px 12px; border-radius: 999px; font-size: 0.95rem; }
        @media (max-width: 768px) {
            .profile-grid { grid-template-columns: 1fr; }
            .current-image { width: 160px; height: 160px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Tutor Profile</h1>
            <p>Manage your public tutor profile, teaching specialties, and availability details.</p>
        </div>
        <div class="nav">
            <a href="dashboard.php">← Back to Dashboard</a>
            <a href="messages.php">Messages</a>
            <a href="settings.php">Settings</a>
        </div>
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="profile-grid">
                    <div class="profile-image-section">
                        <h3>Profile Picture</h3>
                        <?php if ($profile && $profile['profile_image']): ?>
                            <img src="../<?php echo htmlspecialchars($profile['profile_image']); ?>" alt="Profile Image" class="current-image">
                        <?php else: ?>
                            <div class="current-image" style="background:#e2e8f0; display:flex; align-items:center; justify-content:center; font-size:48px; color:#6b7280;">👤</div>
                        <?php endif; ?>
                        <div class="form-group" style="margin-top:24px;">
                            <label for="profile_image">Upload New Image</label>
                            <input type="file" id="profile_image" name="profile_image" accept="image/*">
                        </div>
                    </div>
                    <div class="profile-form">
                        <div class="form-section">
                            <h3>Personal Details</h3>
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="hourly_rate">Hourly Rate</label>
                                <input type="text" id="hourly_rate" name="hourly_rate" value="<?php echo htmlspecialchars($profile['hourly_rate'] ?? ''); ?>" placeholder="e.g. $25/hr">
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Teaching Focus</h3>
                            <div class="form-group">
                                <label for="subjects_taught">Subjects Taught</label>
                                <textarea id="subjects_taught" name="subjects_taught" placeholder="Math, Science, English, Computer Science, etc."><?php echo htmlspecialchars($profile['subjects_taught'] ?? ''); ?></textarea>
                            </div>
                            <?php if ($profile && $profile['subjects_taught']): ?>
                                <div class="tag-list">
                                    <?php
                                    foreach (explode(',', $profile['subjects_taught']) as $subject) {
                                        $subject = trim($subject);
                                        if ($subject) {
                                            echo '<span class="tag-item">' . htmlspecialchars($subject) . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-section">
                            <h3>Professional Background</h3>
                            <div class="form-group">
                                <label for="qualifications">Qualifications</label>
                                <textarea id="qualifications" name="qualifications" placeholder="Degrees, certifications, or training programs."><?php echo htmlspecialchars($profile['qualifications'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="experience">Experience</label>
                                <textarea id="experience" name="experience" placeholder="Years of teaching experience, coaching highlights, and successes."><?php echo htmlspecialchars($profile['experience'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="bio">About You</label>
                                <textarea id="bio" name="bio" placeholder="Describe your tutoring style, approach, and what makes you a great tutor."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
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