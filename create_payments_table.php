<?php
require_once 'config/db.php';

try {
    // Payments table
    $sql = "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        student_id INT NOT NULL,
        tutor_id INT NOT NULL,
        amount DECIMAL(8,2) NOT NULL,
        currency VARCHAR(3) DEFAULT 'KES',
        reference VARCHAR(100) UNIQUE NOT NULL,
        status ENUM('pending', 'paid', 'failed', 'cancelled') DEFAULT 'pending',
        payment_data JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
        INDEX idx_session (session_id),
        INDEX idx_status (status),
        INDEX idx_reference (reference)
    )";
    
    $pdo->exec($sql);
    echo "Payments table created successfully!\n";
    
    // Add to sessions table if not exists
    $check = $pdo->query("SHOW COLUMNS FROM sessions LIKE 'payment_status'")->rowCount();
    if (!$check) {
        $pdo->exec("ALTER TABLE sessions ADD COLUMN payment_status ENUM('unpaid', 'paid') DEFAULT 'unpaid' AFTER status");
        $pdo->exec("ALTER TABLE sessions ADD COLUMN amount DECIMAL(8,2) DEFAULT 0.00 AFTER payment_status");
        echo "Sessions payment columns added!\n";
    } else {
        echo "Sessions payment columns already exist.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

