<?php
require_once 'config/db.php';

// Pre-payment support for sessions
try {
    // Add payment_amount if not exists
    $check = $pdo->query("SHOW COLUMNS FROM sessions LIKE 'payment_amount'")->rowCount();
    if (!$check) {
        $pdo->exec("ALTER TABLE sessions ADD COLUMN payment_amount DECIMAL(8,2) DEFAULT 500.00 AFTER amount");
        echo "payment_amount column added (default KSh 500)\n";
    }
    
    // Update payment_status to 'pending_payment' for new bookings
    $pdo->exec("UPDATE sessions SET payment_status = 'pending_payment' WHERE payment_status = 'unpaid' OR payment_status IS NULL");
    
    // Ensure payments table has all indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_session ON payments(session_id)");
    
    echo "Pre-payment support enabled! Sessions now require payment at booking.\n";
    echo "Default rate: KSh 500/session (edit DB or code)\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

