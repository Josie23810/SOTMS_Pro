<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['student']);

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = intval($_POST['tutor_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $message_text = trim($_POST['message'] ?? '');

    if (empty($receiver_id) || empty($subject) || empty($message_text)) {
        $message = "All fields are required.";
        $messageType = 'error';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)');
            $stmt->execute([$_SESSION['user_id'], $receiver_id, $subject, $message_text]);
            
            $message = "Message sent successfully!";
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = "Error sending message: " . $e->getMessage();
            $messageType = 'error';
            error_log('Message send error: ' . $e->getMessage());
        }
    }
}

// Get available tutors (for now, we'll show all tutors)
$tutors = [];
try {
    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE role = "tutor" ORDER BY name');
    $stmt->execute();
    $tutors = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Tutors fetch error: ' . $e->getMessage());
}

$preselected_tutor_id = intval($_GET['to'] ?? 0);
$reply_subject = '';
$reply_message_seed = '';

if (isset($_GET['reply_to'])) {
    $reply_to = intval($_GET['reply_to']);
    if ($reply_to > 0) {
        try {
            $stmt = $pdo->prepare('
                SELECT m.*, u.name as sender_name
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.id = ? AND m.receiver_id = ?
                LIMIT 1
            ');
            $stmt->execute([$reply_to, $_SESSION['user_id']]);
            $original = $stmt->fetch();

            if ($original) {
                $preselected_tutor_id = intval($original['sender_id']);
                $cleanSubject = trim($original['subject'] ?? '');
                $reply_subject = (stripos($cleanSubject, 'Re:') === 0) ? $cleanSubject : 'Re: ' . $cleanSubject;
                $reply_message_seed =
                    "\n\n--- Original Message ---\n" .
                    "From: " . ($original['sender_name'] ?? 'Tutor') . "\n" .
                    "Sent: " . date('M j, Y g:i A', strtotime($original['created_at'])) . "\n" .
                    $original['message'];
            }
        } catch (PDOException $e) {
            error_log('Reply context fetch error (student): ' . $e->getMessage());
        }
    }
}

// Get sent messages
$sent_messages = [];
try {
    $stmt = $pdo->prepare('
        SELECT m.*, u.name as receiver_name 
        FROM messages m 
        JOIN users u ON m.receiver_id = u.id 
        WHERE m.sender_id = ? 
        ORDER BY m.created_at DESC 
        LIMIT 10
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $sent_messages = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Sent messages fetch error: ' . $e->getMessage());
}

// Get received messages
$received_messages = [];
try {
    $stmt = $pdo->prepare('
        SELECT m.*, u.name as sender_name 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.receiver_id = ? 
        ORDER BY m.created_at DESC 
        LIMIT 10
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $received_messages = $stmt->fetchAll();

    $stmt = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND is_read = 0');
    $stmt->execute([$_SESSION['user_id']]);
} catch (PDOException $e) {
    error_log('Received messages fetch error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - SOTMS PRO</title>
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
        .message-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .compose-section, .messages-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
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
            min-height: 120px;
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
        .message-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .message-item h4 {
            margin: 0 0 5px;
            color: #1f2937;
        }
        .message-item .meta {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .message-item .content {
            color: #374151;
        }
        @media (max-width: 768px) {
            .message-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Messages</h1>
            <p>Communicate with your tutors</p>
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

            <div class="message-grid">
                <div class="compose-section">
                    <h2>Send Message</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label for="tutor_id">Select Tutor</label>
                            <select id="tutor_id" name="tutor_id" required>
                                <option value="">Choose a tutor...</option>
                                <?php
                                    $selectedTutor = isset($_POST['tutor_id']) ? intval($_POST['tutor_id']) : $preselected_tutor_id;
                                ?>
                                <?php foreach ($tutors as $tutor): ?>
                                    <option value="<?php echo $tutor['id']; ?>" <?php echo $selectedTutor === intval($tutor['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tutor['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" required placeholder="Brief subject line" value="<?php echo htmlspecialchars($_POST['subject'] ?? $reply_subject); ?>">
                        </div>
                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" required placeholder="Write your message here..."><?php echo htmlspecialchars($_POST['message'] ?? $reply_message_seed); ?></textarea>
                        </div>
                        <button type="submit" class="btn">Send Message</button>
                    </form>
                </div>

                <div class="messages-section">
                    <h2>Received Messages</h2>
                    <?php if (empty($received_messages)): ?>
                        <p>No received messages yet.</p>
                    <?php else: ?>
                        <?php foreach ($received_messages as $msg): ?>
                            <div class="message-item">
                                <h4><?php echo htmlspecialchars($msg['subject']); ?></h4>
                                <div class="meta">From: <?php echo htmlspecialchars($msg['sender_name']); ?> • <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></div>
                                <div class="content"><?php echo nl2br(htmlspecialchars(substr($msg['message'], 0, 150))); ?><?php echo strlen($msg['message']) > 150 ? '...' : ''; ?></div>
                                <div style="margin-top:10px;">
                                    <a class="btn" style="padding:8px 14px; font-size:14px;" href="messages.php?reply_to=<?php echo intval($msg['id']); ?>">Reply</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <hr style="margin: 26px 0; border-top: 1px solid #d1d5db;">

                    <h2>Sent Messages</h2>
                    <?php if (empty($sent_messages)): ?>
                        <p>No messages sent yet.</p>
                    <?php else: ?>
                        <?php foreach ($sent_messages as $msg): ?>
                            <div class="message-item">
                                <h4><?php echo htmlspecialchars($msg['subject']); ?></h4>
                                <div class="meta">To: <?php echo htmlspecialchars($msg['receiver_name']); ?> • <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></div>
                                <div class="content"><?php echo nl2br(htmlspecialchars(substr($msg['message'], 0, 150))); ?><?php echo strlen($msg['message']) > 150 ? '...' : ''; ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>