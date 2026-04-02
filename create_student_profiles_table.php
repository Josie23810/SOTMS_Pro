<?php
require_once 'config/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS student_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        profile_image VARCHAR(255),
        full_name VARCHAR(100),
        phone VARCHAR(20),
        education_level ENUM('high_school', 'college', 'university', 'masters', 'phd', 'other') DEFAULT 'high_school',
        current_institution VARCHAR(100),
        subjects_interested TEXT,
        bio TEXT,
        goals TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    $pdo->exec($sql);
    echo "Student profiles table created successfully!\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>