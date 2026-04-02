-- Create sessions table for the tutoring management system
CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    preferred_date DATE NOT NULL,
    preferred_time TIME NOT NULL,
    duration INT DEFAULT 60, -- in minutes
    notes TEXT,
    meeting_link VARCHAR(500) NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add some sample data (optional)
-- INSERT INTO sessions (student_id, subject, preferred_date, preferred_time, notes) VALUES
-- (1, 'Mathematics', '2024-01-15', '14:00:00', 'Need help with calculus');