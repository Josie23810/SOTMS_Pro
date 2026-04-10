<?php
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/user_helpers.php';
checkAccess(['admin']);

$missing = missingPlatformRequirements($pdo);
$ready = empty($missing);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Readiness - SOTMS PRO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(rgba(15,23,42,0.7), rgba(15,23,42,0.7)), url('../uploads/image005.jpg') center/cover fixed; margin:0; padding:20px; }
        .container { max-width:1000px; margin:0 auto; background:rgba(255,255,255,0.96); border-radius:20px; overflow:hidden; box-shadow:0 25px 50px rgba(0,0,0,0.2); }
        .header { background:linear-gradient(135deg, #0f766e, #14b8a6); color:white; padding:25px; text-align:center; }
        .content { padding:30px; }
        .status-card { border-radius:16px; padding:22px; margin-bottom:24px; border:1px solid; }
        .status-ok { background:#d1fae5; color:#065f46; border-color:#a7f3d0; }
        .status-bad { background:#fee2e2; color:#991b1b; border-color:#fecaca; }
        .code-block { background:#0f172a; color:#e2e8f0; border-radius:14px; padding:18px; font-family: Consolas, monospace; overflow:auto; }
        .list { margin:0; padding-left:20px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; background:#2563eb; color:white; text-decoration:none; border-radius:10px; padding:12px 18px; font-weight:700; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>System Readiness</h1>
            <p>Check whether the required foundation, product-model, and operations workflow migrations have been applied.</p>
        </div>
        <div class="content">
            <div class="status-card <?php echo $ready ? 'status-ok' : 'status-bad'; ?>">
                <strong><?php echo $ready ? 'Schema ready' : 'Schema incomplete'; ?></strong>
                <div style="margin-top:8px;">
                    <?php if ($ready): ?>
                        All required foundation, Phase 3, and Phase 4 tables and columns are present.
                    <?php else: ?>
                        Some required tables or columns are missing. Run the migration script before using the updated application.
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$ready): ?>
                <h3>Missing Requirements</h3>
                <ul class="list">
                    <?php foreach ($missing as $item): ?>
                        <li><?php echo htmlspecialchars($item); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h3>Migration Commands</h3>
            <div class="code-block">php run_phase1_migration.php
php run_phase3_migration.php
php run_phase4_migration.php</div>

            <div style="margin-top:24px;">
                <a href="dashboard.php" class="btn">Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
