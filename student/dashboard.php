<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['student']); // Only allow students

// Get session statistics
$upcomingSessions = 0;
$completedSessions = 0;

try {
    $studentId = getStudentId($pdo, $_SESSION['user_id']);

    if ($studentId) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM sessions s
            LEFT JOIN students st ON s.student_id = st.id
            WHERE st.user_id = ?
              AND s.status = "confirmed"
              AND s.session_date >= NOW()
        ');
        $stmt->execute([$_SESSION['user_id']]);
        $upcomingSessions = $stmt->fetchColumn();

        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM sessions s
            LEFT JOIN students st ON s.student_id = st.id
            WHERE st.user_id = ?
              AND s.status = "completed"
        ');
        $stmt->execute([$_SESSION['user_id']]);
        $completedSessions = $stmt->fetchColumn();
    }

    // Load profile image for top-right avatar
    $stmt = $pdo->prepare('SELECT profile_image FROM student_profiles WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $profileData = $stmt->fetch();
    $profileImage = $profileData['profile_image'] ?? null;

    // Load tutors + profile previews for student usage (all tutors, optionally profile record)
    $tutors = [];
    $stmt = $pdo->prepare('SELECT u.id AS user_id, t.id AS tutor_id, u.name, u.email, tp.profile_image, tp.subjects_taught, tp.qualifications, tp.bio, tp.experience, tp.hourly_rate
        FROM users u
        LEFT JOIN tutors t ON t.user_id = u.id
        LEFT JOIN tutor_profiles tp ON tp.user_id = u.id
        WHERE u.role = "tutor"
        ORDER BY u.name
        LIMIT 5');
    $stmt->execute();
    $tutors = $stmt->fetchAll();
} catch (PDOException $e) {
    $profileImage = null;
    error_log('Dashboard stats error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - SOTMS PRO</title>
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
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255,255,255,0.95);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(15,23,42,0.15);
            overflow: hidden;
            display: flex;
            min-height: 80vh;
        }
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #1e293b, #0f172a);
            padding: 30px 20px;
            color: white;
        }
        .sidebar h2 {
            margin: 0 0 20px;
            font-size: 1.2rem;
            color: #e2e8f0;
        }
        .sidebar .btn {
            display: block;
            width: 100%;
            margin-bottom: 10px;
            text-align: left;
            background: rgba(255,255,255,0.1);
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .sidebar .btn.schedule-btn {
            background: #2563eb;
            color: white !important;
            font-size: 18px;
            font-weight: 700;
            padding: 16px 18px;
            box-shadow: 0 10px 20px rgba(37,99,235,0.25);
        }
        .sidebar .btn.schedule-btn:hover {
            background: #1d4ed8;
            transform: translateX(2px);
        }
        .sidebar .btn:hover, .sidebar .btn.active {
            background: #2563eb;
            transform: translateX(5px);
        }
        .sidebar .logout-btn {
            margin-top: 30px;
            background: rgba(239,68,68,0.8);
        }
        .sidebar .logout-btn:hover {
            background: #dc2626;
        }
        .main-content {
            flex: 1;
            padding: 30px;
        }
        .header {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }
        .header-content {
            max-width: calc(100% - 240px);
        }
        .header h1 {
            margin: 0;
            font-size: 2.5rem;
        }
        .header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
        }
        .profile-dropdown {
            position: relative;
            cursor: pointer;
        }
        .profile-trigger {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 12px 14px 14px;
            background: rgba(255,255,255,0.18);
            border-radius: 20px;
            transition: background 0.2s;
            min-width: 110px;
        }
        .profile-trigger:hover {
            background: rgba(255,255,255,0.28);
        }
        .profile-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.9);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.2);
        }
        .profile-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-icon span {
            font-size: 1.6rem;
            color: white;
        }
        .profile-name {
            color: white;
            font-weight: 700;
            text-align: center;
            font-size: 0.95rem;
            line-height: 1.2;
        }
        .profile-role {
            color: rgba(255,255,255,0.8);
            font-size: 0.82rem;
            text-align: center;
        }
        .profile-menu {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            min-width: 220px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 20px 40px rgba(15,23,42,0.18);
            display: none;
            z-index: 20;
            overflow: hidden;
        }
        .profile-menu.open {
            display: block;
        }
        .profile-menu a {
            display: block;
            padding: 14px 18px;
            color: #1f2937;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.15s;
        }
        .profile-menu a:hover {
            background: #f8fafc;
        }
        .profile-menu .menu-title {
            padding: 18px;
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
            font-weight: 700;
        }
        .main-content {
            flex: 1;
            padding: 30px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .stat-card h3 {
            margin: 0 0 10px;
            color: #374151;
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #2563eb;
        }
        .sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        .section h2 {
            margin: 0 0 15px;
            color: #1f2937;
        }
        .section ul {
            list-style: none;
            padding: 0;
        }
        .section li {
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .section li:last-child {
            border-bottom: none;
        }
        .section a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }
        .section a:hover {
            text-decoration: underline;
        }
        .btn {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin: 10px 10px 10px 0;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                padding: 20px;
            }
            .sidebar .btn {
                display: inline-block;
                width: auto;
                margin: 5px;
                flex: 1;
                min-width: 120px;
            }
            .sections {
                grid-template-columns: 1fr;
            }
            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="sidebar">
            <h2>Quick Actions</h2>
            <a href="book_session.php" class="btn schedule-btn">📅 Schedule Session</a>
            <a href="schedule.php" class="btn">📆 My Sessions</a>
            <a href="find_tutors.php" class="btn">👨‍🏫 Find Tutors</a>
            <a href="profile.php" class="btn">👤 My Profile</a>
            <a href="messages.php" class="btn">💬 Messages</a>
            <a href="resources.php" class="btn">📚 Resources</a>
            <a href="../config/auth/logout.php" class="btn logout-btn">🚪 Logout</a>
        </div>
        
        <div class="main-content">
            <div class="header">
                <div class="header-content">
                    <h1>Welcome to Your Dashboard</h1>
                    <p>Hello, <?php echo htmlspecialchars($_SESSION['name']); ?>! Here's your learning overview.</p>
                </div>
                <div class="header-actions">
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="profile-trigger" id="profileTrigger">
                            <div class="profile-icon">
                                <?php if (!empty($profileImage)): ?>
                                    <img src="../<?php echo htmlspecialchars($profileImage); ?>" alt="Profile Image">
                                <?php else: ?>
                                    <span><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="profile-name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                            <div class="profile-role">Student Profile</div>
                        </div>
                        <div class="profile-menu" id="profileMenu">
                            <div class="menu-title">Account</div>
                            <a href="profile.php">View Profile</a>
                            <a href="settings.php">Settings</a>
                            <a href="messages.php">Messages</a>
                            <a href="schedule.php">My Sessions</a>
                            <a href="../config/auth/logout.php">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        
        <div class="content">
            <?php if (isset($_SESSION['booking_success'])): ?>
                <div style="background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 12px; padding: 16px; margin-bottom: 24px; font-weight: 600;">
                    <?php echo htmlspecialchars($_SESSION['booking_success']); ?>
                </div>
                <?php unset($_SESSION['booking_success']); ?>
            <?php endif; ?>
            <div class="stats">
                <div class="stat-card">
                    <h3>Upcoming Sessions</h3>
                    <div class="number"><?php echo $upcomingSessions; ?></div>
                    <p><?php echo $upcomingSessions > 0 ? 'Sessions scheduled' : 'No sessions scheduled'; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Completed Sessions</h3>
                    <div class="number"><?php echo $completedSessions; ?></div>
                    <p><?php echo $completedSessions > 0 ? 'Sessions completed' : 'Start learning today!'; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Available Tutors</h3>
                    <div class="number">--</div>
                    <p>Browse our tutor directory</p>
                </div>
                <div class="stat-card">
                    <h3>Tutor Uploaded Resources</h3>
                    <div class="number">📚</div>
                    <p>Open all tutor-shared materials.</p>
                    <a href="resources.php" class="btn">View Resources</a>
                </div>
            </div>
            
            <div class="sections">
                <div class="section">
                    <h2>Recent Activity</h2>
                    <ul>
                        <li>Welcome to SOTMS PRO! Complete your profile to get started.</li>
                        <li>No recent sessions. Book your first tutoring session today.</li>
                        <li>Explore available resources in the Resources section.</li>
                    </ul>
                </div>
                
            <div class="section">
    <h2>My Sessions</h2>
    <?php if ($upcomingSessions > 0): ?>
        <p>You have <?php echo $upcomingSessions; ?> upcoming session(s).</p>
        
        <?php 
        // 1. FETCH DATA FIRST
        try {
            $stmt = $pdo->prepare('
                SELECT s.id, s.title, s.session_date, s.status, t.name as tutor_name
                FROM sessions s
                LEFT JOIN students st ON s.student_id = st.id
                LEFT JOIN tutors t ON s.tutor_id = t.id
                WHERE st.user_id = ? AND s.status IN ("confirmed", "scheduled")
                ORDER BY s.session_date ASC
                LIMIT 3
            ');
            $stmt->execute([$_SESSION['user_id']]);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $sessions = []; // Fallback to empty array if query fails
        }
        ?>

        <?php if (!empty($sessions)): ?>
            <?php foreach ($sessions as $session): ?>
                <div class="session-card" style="border:1px solid #e5e7eb; border-radius:10px; padding:16px; margin-bottom:12px; background:#ffffff;">
                    <h4><?php echo htmlspecialchars($session['title']); ?></h4>
                    <p>👨‍🏫 <?php echo htmlspecialchars($session['tutor_name'] ?? 'Tutor not assigned'); ?></p>
                    <p>📅 <?php echo date('M j, Y g:i A', strtotime($session['session_date'])); ?></p>
                    <p>💰 KSh <span id="amount-<?php echo $session['id']; ?>">500</span></p>
                    
                    <button onclick="joinSession(<?php echo $session['id']; ?>, 500)" 
                            class="btn" style="background:#10b981; font-size:14px; padding:8px 16px; border:none; cursor:pointer; color:white; border-radius:8px;">
                        💳 Join with M-Pesa
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div style="margin-top: 15px;">
            <a href="schedule.php" class="btn">View All Sessions</a>
            <a href="resources.php" class="btn" style="background:#6b7280;">View Resources</a>
        </div>

    <?php else: ?>
        <p>You have no upcoming sessions.</p>
        <a href="book_session.php" class="btn">Schedule a Session</a>
        <a href="resources.php" class="btn" style="background:#6b7280;">View Resources</a>
    <?php endif; ?>
</div> 
                <!-- Payment Modal -->
    <div id="paymentModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div style="background:white; margin:10% auto; padding:30px; border-radius:16px; max-width:450px; box-shadow:0 20px 40px rgba(0,0,0,0.3);">
            <h3 style="margin:0 0 20px; color:#1f2937;">Pay to Join Session</h3>
            <div id="sessionDetails" style="background:#f8fafc; padding:16px; border-radius:12px; margin-bottom:20px;"></div>
            <input type="tel" id="phoneInput" placeholder="2547XXXXXXXX" style="width:100%; padding:12px; border:1px solid #d1d5db; border-radius:8px; margin-bottom:16px; font-size:16px;">
            <button onclick="initiatePayment()" id="payButton" class="btn" style="width:100%; background:#10b981; margin-bottom:12px;">💳 Pay with M-Pesa</button>
            <button onclick="checkPaymentStatus()" class="btn" style="width:48%; background:#2563eb;">Check Status</button>
            <button onclick="closeModal()" class="btn" style="width:48%; background:#6b7280;">Close</button>
            <div id="paymentStatus"></div>
        </div>
    </div>
</div>
                
                <div class="section">
                    <h2>Recommended Tutors</h2>
                    <?php if (empty($tutors)): ?>
                    <?php else: ?>
                        <?php foreach ($tutors as $t): ?>
                            <div style="border:1px solid #e5e7eb; border-radius:10px; padding:12px; margin-bottom:10px; background:#ffffff;">
                                <strong><?php echo htmlspecialchars($t['name']); ?></strong>
                                <p style="margin:4px 0; color:#6b7280;"><?php echo htmlspecialchars($t['subjects_taught'] ?? ''); ?></p>
                                <p style="margin:4px 0; font-size:.92rem; color:#475569;"><?php echo htmlspecialchars(strlen($t['bio']) ? substr($t['bio'], 0, 120) . (strlen($t['bio']) > 120 ? '...' : '') : ''); ?></p>
                                <div style="margin-top: 8px; font-size:.9rem; color:#0f172a; font-weight:600;">
                                    <?php if (!empty($t['hourly_rate'])): ?>
                                        Rate: <?php echo htmlspecialchars($t['hourly_rate']); ?>
                                    <?php endif; ?>
                                </div>
                                <a href="book_session.php?tutor=<?php echo $t['tutor_id']; ?>" class="btn" style="margin-top:8px;">Book a Session</a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="section">
                    <h2>Quick Links</h2>
                    <ul>
                        <li><a href="profile.php">Update your profile information</a></li>
                        <li><a href="messages.php">Send messages to tutors</a></li>
                        <li><a href="find_tutors.php">Browse available tutors</a></li>
                        <li><a href="resources.php">Access tutor uploaded resources</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <script>
        const profileTrigger = document.getElementById('profileTrigger');
        const profileMenu = document.getElementById('profileMenu');
        const profileDropdown = document.getElementById('profileDropdown');

        profileTrigger.addEventListener('click', function(event) {
            event.stopPropagation();
            profileMenu.classList.toggle('open');
        });

        document.addEventListener('click', function() {
            profileMenu.classList.remove('open');
        });

        profileDropdown.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    </script>
    <script>
// Global variables
let currentSessionId = null;
let currentPaymentId = null;

// Join session - show payment modal
function joinSession(sessionId, amount) {
    currentSessionId = sessionId;
    document.getElementById('phoneInput').value = '';
    document.getElementById('paymentStatus').innerHTML = '';
    document.getElementById('paymentModal').style.display = 'block';
    
    // Show session details
    document.getElementById('sessionDetails').innerHTML = `
        <strong>Session ID: ${sessionId}</strong><br>
        Amount: KSh ${amount}<br>
        <small>Enter M-Pesa PIN on your phone after clicking Pay</small>
    `;
}

async function initiatePayment() {
    const phone = document.getElementById('phoneInput').value.trim();
    const statusDiv = document.getElementById('paymentStatus');
    const payBtn = document.getElementById('payButton');

    // 1. Validation: Must be 254... 12 digits
    if (!phone.match(/^254[17]\d{8}$/)) {
        statusDiv.innerHTML = '<div style="color:#ef4444; font-weight:bold;">❌ Format: 2547XXXXXXXX</div>';
        return;
    }

    payBtn.innerHTML = '⏳ Processing...';
    payBtn.disabled = true;
    statusDiv.innerHTML = '<div style="color:#2563eb;">📡 Requesting M-Pesa Prompt...</div>';

    try {
        // 2. Fetch from your Node.js server (Port 8000)
        const response = await fetch('http://localhost:8000/api/mpesa/stkpush', { 
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                phone: phone,
                amount: 500, // Or use a dynamic variable
                reference: "SOTMS" + currentSessionId, // REPLACED DASH WITH STRING CONCAT
                description: "Tutor Session Payment"
            })
        });

        const data = await response.json();

        if (data.success) {
            statusDiv.innerHTML = `
                <div style="background:#d1fae5; color:#065f46; padding:12px; border-radius:8px; border:1px solid #a7f3d0;">
                    ✅ <b>PIN Prompt Sent!</b><br>
                    Please enter your M-Pesa PIN on your phone now.
                </div>
            `;
            // Start polling for the DB update
            checkPaymentStatus(currentSessionId);
        } else {
            // Show the actual Safaricom error (e.g., Invalid Callback)
            statusDiv.innerHTML = `<div style="color:#ef4444; font-weight:bold;">❌ ${data.error || 'Request Rejected'}</div>`;
            payBtn.disabled = false;
            payBtn.innerHTML = '💳 Retry Payment';
        }
    } catch (error) {
        statusDiv.innerHTML = '<div style="color:#ef4444;">❌ Server Connection Failed. Is Node running?</div>';
        payBtn.disabled = false;
        payBtn.innerHTML = '💳 Retry Connection';
    }
}

    // UPDATED: This now talks to your Node.js server on Port 8000
const response = await fetch('http://localhost:8000/api/mpesa/stkpush', { 
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        phone: phone,               // Matches req.body.phone in server.js
        amount: <?= $amount ?>,      // Your PHP session amount
        reference: "SOTMS-<?= $session_id ?>", // Matches req.body.reference
        description: "Tutor Session Payment"
    })
});
        
        const data = await response.json();
        
        if (data.success) {
            currentPaymentId = data.payment_id;
            document.getElementById('paymentStatus').innerHTML = `
                <div style="background:#d1fae5; color:#065f46; padding:12px; border-radius:8px;">
                    ✅ M-Pesa PIN sent to ${phone}<br>
                    Reference: ${data.reference}<br>
                    <button onclick="checkPaymentStatus()" style="margin-top:8px;">Check Status</button>
                </div>
            `;
        } else {
            throw new Error(data.error || 'Payment failed');
        }
    } catch (error) {
        document.getElementById('paymentStatus').innerHTML = `<div style="color:#ef4444;">${error.message}</div>`;
    } finally {
        document.getElementById('payButton').innerHTML = '💳 Pay with M-Pesa';
        document.getElementById('payButton').disabled = false;
    }
}

// Check payment status
async function checkPaymentStatus() {
    if (!currentPaymentId) return;
    
    try {
        const response = await fetch(`api/mpesa.php?payment_id=${currentPaymentId}`);
        const payment = await response.json();
        
        if (payment.status === 'SUCCESS') {
            document.getElementById('paymentStatus').innerHTML = `
                <div style="background:#d1fae5; color:#065f46; padding:16px; border-radius:12px; text-align:center;">
                    ✅ Payment Successful!<br>
                    Receipt: ${payment.mpesa_receipt}<br>
                    <a href="join_session.php?session_id=${payment.session_id}" class="btn" style="width:100%; margin-top:12px;">
                        🎉 Enter Session Room
                    </a>
                </div>
            `;
        } else if (payment.status === 'PENDING') {
            document.getElementById('paymentStatus').innerHTML += '<div>⏳ Still processing...</div>';
            setTimeout(checkPaymentStatus, 3000);
        }
    } catch (error) {
        console.error('Status check error:', error);
    }
}

function closeModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

// Close modal on outside click
document.getElementById('paymentModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>