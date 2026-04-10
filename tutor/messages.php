<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['tutor']);

$message = '';
$messageType = '';
$students = [];
$receivedMessages = [];
$reply_subject = '';
$reply_message_seed = '';
$preselected_receiver_id = intval($_GET['to'] ?? 0);

try {
    $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE role = "student" ORDER BY name');
    $stmt->execute();
    $students = $stmt->fetchAll();

    if (isset($_GET['reply_to'])) {
        $reply_to = intval($_GET['reply_to']);
        if ($reply_to > 0) {
            $stmt = $pdo->prepare('
                SELECT m.*, u.name AS sender_name
                FROM messages m
                LEFT JOIN users u ON m.sender_id = u.id
                WHERE m.id = ? AND m.receiver_id = ?
                LIMIT 1
            ');
            $stmt->execute([$reply_to, $_SESSION['user_id']]);
            $original = $stmt->fetch();

            if ($original) {
                $preselected_receiver_id = intval($original['sender_id']);
                $cleanSubject = trim($original['subject'] ?? '');
                $reply_subject = (stripos($cleanSubject, 'Re:') === 0) ? $cleanSubject : 'Re: ' . $cleanSubject;
                $reply_message_seed =
                    "\n\n--- Original Message ---\n" .
                    "From: " . ($original['sender_name'] ?: 'Student') . "\n" .
                    "Sent: " . date('M j, Y g:i A', strtotime($original['created_at'])) . "\n" .
                    $original['message'];
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $receiver_id = intval($_POST['receiver_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['message'] ?? '');

        if (!$receiver_id || empty($subject) || empty($body)) {
            $message = 'All fields are required to send a message.';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)');
            $stmt->execute([$_SESSION['user_id'], $receiver_id, $subject, $body]);
            $message = 'Message sent successfully.';
            $messageType = 'success';
        }
    }

    $stmt = $pdo->prepare('SELECT m.*, u.name AS sender_name FROM messages m LEFT JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = ? ORDER BY m.created_at DESC');
    $stmt->execute([$_SESSION['user_id']]);
    $receivedMessages = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT m.*, u.name AS receiver_name FROM messages m LEFT JOIN users u ON m.receiver_id = u.id WHERE m.sender_id = ? ORDER BY m.created_at DESC');
    $stmt->execute([$_SESSION['user_id']]);
    $sentMessages = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Tutor messages error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Tutor Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family:'Poppins',sans-serif; background: linear-gradient(180deg,rgba(15,23,42,0.6),rgba(15,23,42,0.6)),url('../uploads/image003.jpg') center/cover no-repeat; margin:0; color:#1f2937; padding:20px; }
        .container { max-width:1100px; margin:0 auto; background:rgba(255,255,255,0.96); border-radius:20px; overflow:hidden; box-shadow:0 24px 50px rgba(15,23,42,0.18); }
        .header { background: linear-gradient(135deg,#2563eb,#1d4ed8); color:white; padding:30px; }
        .header h1 { margin:0; font-size:2.4rem; }
        .nav { padding:20px; background:#f8fafc; border-bottom:1px solid #e5e7eb; }
        .nav a { color:#2563eb; margin-right:18px; text-decoration:none; font-weight:600; }
        .content { padding:30px; }
        .message-box { display:grid; grid-template-columns:1fr 1fr; gap:28px; }
        .card { background:white; border-radius:18px; border:1px solid #e5e7eb; padding:26px; box-shadow:0 12px 28px rgba(15,23,42,0.08); }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; margin-bottom:8px; font-weight:700; }
        .form-group input, .form-group select, .form-group textarea { width:100%; padding:14px; border:1px solid #d1d5db; border-radius:12px; }
        .form-group textarea { min-height:140px; resize:vertical; }
        .btn { display:inline-flex; align-items:center; justify-content:center; background:#2563eb; color:white; border:none; border-radius:12px; padding:12px 20px; text-decoration:none; font-weight:700; cursor:pointer; }
        .btn:hover { background:#1d4ed8; }
        .message-item { border:1px solid #e5e7eb; border-radius:16px; padding:18px; margin-bottom:16px; background:#f9fafb; }
        .message-item h3 { margin:0 0 8px; }
        .message-meta { color:#6b7280; font-size:0.95rem; margin-bottom:12px; }
        .message-body { color:#374151; line-height:1.7; }
        .note { margin-bottom:20px; color:#475569; }
        .message { padding:16px; border-radius:14px; margin-bottom:20px; }
        .success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
        .error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        @media(max-width:960px){ .message-box { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Messages</h1>
            <p>View incoming student messages and send quick replies.</p>
        </div>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="schedule.php">Schedule</a>
            <a href="upload_materials.php">Upload Materials</a>
            <a href="profile.php">Profile</a>
            <a href="settings.php">Settings</a>
        </div>
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <div class="message-box">
                <div class="card">
                    <h2>Send a New Message</h2>
                    <div class="note">Select a student from your list, add a subject, and send a private message.</div>
                    <form method="POST">
                        <div class="form-group">
                            <label for="receiver_id">Student</label>
                            <select id="receiver_id" name="receiver_id" required>
                                <option value="">Choose a student</option>
                                <?php $selectedReceiver = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : $preselected_receiver_id; ?>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" <?php echo $selectedReceiver === intval($student['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($student['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" required value="<?php echo htmlspecialchars($_POST['subject'] ?? $reply_subject); ?>">
                        </div>
                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" required><?php echo htmlspecialchars($_POST['message'] ?? $reply_message_seed); ?></textarea>
                        </div>
                        <button type="submit" class="btn">Send Message</button>
                    </form>
                </div>

                <div class="card">
                    <h2>Received Messages</h2>
                    <?php if (empty($receivedMessages)): ?>
                        <p style="color:#475569;">You have no messages yet. Students can message you from their dashboard.</p>
                    <?php else: ?>
                        <?php foreach ($receivedMessages as $msg): ?>
                            <div class="message-item">
                                <h3><?php echo htmlspecialchars($msg['subject']); ?></h3>
                                <div class="message-meta">From: <?php echo htmlspecialchars($msg['sender_name'] ?: 'Student'); ?> • <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></div>
                                <p class="message-body"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                <div style="margin-top:10px;">
                                    <a class="btn" style="padding:8px 14px; font-size:14px;" href="messages.php?reply_to=<?php echo intval($msg['id']); ?>">Reply</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <hr style="margin: 24px 0; border-top: 1px solid #e5e7eb;">

                    <h2>Sent Messages</h2>
                    <?php if (empty($sentMessages)): ?>
                        <p style="color:#475569;">You have not sent any messages yet.</p>
                    <?php else: ?>
                        <?php foreach ($sentMessages as $msg): ?>
                            <div class="message-item">
                                <h3><?php echo htmlspecialchars($msg['subject']); ?></h3>
                                <div class="message-meta">To: <?php echo htmlspecialchars($msg['receiver_name'] ?: 'Student'); ?> • <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></div>
                                <p class="message-body"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>