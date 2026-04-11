CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_key VARCHAR(150) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'tutor', 'admin') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role VARCHAR(20) NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    browser_fingerprint VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_role (user_id, role),
    INDEX idx_session_token (session_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS students (
    id INT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutors (
    id INT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    profile_image VARCHAR(255) NULL,
    full_name VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    education_level VARCHAR(50) DEFAULT 'high_school',
    current_institution VARCHAR(100) NULL,
    subjects_interested TEXT NULL,
    bio TEXT NULL,
    goals TEXT NULL,
    curriculum VARCHAR(100) NULL,
    level_of_study VARCHAR(100) NULL,
    location VARCHAR(150) NULL,
    guardian_name VARCHAR(100) NULL,
    guardian_phone VARCHAR(20) NULL,
    preferred_radius_km INT DEFAULT 50,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    profile_image VARCHAR(255) NULL,
    full_name VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(150) NULL,
    id_number VARCHAR(100) NULL,
    age INT NULL,
    subjects_taught TEXT NULL,
    curriculum_specialties TEXT NULL,
    study_levels_supported TEXT NULL,
    qualifications TEXT NULL,
    qualification_document VARCHAR(255) NULL,
    bio TEXT NULL,
    experience TEXT NULL,
    hourly_rate VARCHAR(50) NULL,
    location VARCHAR(150) NULL,
    service_areas TEXT NULL,
    availability_days TEXT NULL,
    availability_start VARCHAR(20) NULL,
    availability_end VARCHAR(20) NULL,
    max_sessions_per_day INT NULL,
    verification_status VARCHAR(30) DEFAULT 'submitted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    tutor_id INT NULL,
    subject VARCHAR(100) NOT NULL,
    curriculum VARCHAR(100) NULL,
    study_level VARCHAR(100) NULL,
    session_date DATETIME NOT NULL,
    preferred_date DATE NULL,
    preferred_time TIME NULL,
    duration INT DEFAULT 60,
    notes TEXT NULL,
    meeting_link VARCHAR(500) NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    payment_status VARCHAR(30) DEFAULT 'unpaid',
    amount DECIMAL(8,2) DEFAULT 500.00,
    payment_amount DECIMAL(8,2) DEFAULT 500.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (tutor_id) REFERENCES tutors(id) ON DELETE SET NULL,
    INDEX idx_session_date (session_date),
    INDEX idx_tutor_session (tutor_id, session_date),
    INDEX idx_student_session (student_id, session_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    subject VARCHAR(100) NULL,
    curriculum VARCHAR(100) NULL,
    study_level VARCHAR(100) NULL,
    description TEXT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tutor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    tutor_id INT NOT NULL,
    amount DECIMAL(8,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'KES',
    provider VARCHAR(30) DEFAULT 'manual',
    tracking_id VARCHAR(100) NULL,
    reference VARCHAR(100) NOT NULL UNIQUE,
    status VARCHAR(30) DEFAULT 'pending',
    pesapal_txn_id VARCHAR(100) NULL,
    paypal_payment_id VARCHAR(100) NULL,
    payment_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    INDEX idx_payment_session (session_id),
    INDEX idx_payment_status (status),
    INDEX idx_payment_reference (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO schema_migrations (migration_key)
VALUES ('001_phase1_foundation')
ON DUPLICATE KEY UPDATE migration_key = migration_key;
