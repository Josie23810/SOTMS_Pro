-- Fix missing columns that ensurePlatformStructures is looking for

-- Add missing columns to students table
ALTER TABLE students ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Add missing columns to tutors table  
ALTER TABLE tutors ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Add missing columns to student_profiles table
ALTER TABLE student_profiles 
ADD COLUMN IF NOT EXISTS curriculum VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS level_of_study VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS location VARCHAR(150) NULL,
ADD COLUMN IF NOT EXISTS guardian_name VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS guardian_phone VARCHAR(20) NULL,
ADD COLUMN IF NOT EXISTS preferred_radius_km INT DEFAULT 50;

-- Add missing columns to tutor_profiles table
ALTER TABLE tutor_profiles 
ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL,
ADD COLUMN IF NOT EXISTS id_number VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS age INT NULL,
ADD COLUMN IF NOT EXISTS curriculum_specialties TEXT NULL,
ADD COLUMN IF NOT EXISTS study_levels_supported TEXT NULL,
ADD COLUMN IF NOT EXISTS location VARCHAR(150) NULL,
ADD COLUMN IF NOT EXISTS service_areas TEXT NULL,
ADD COLUMN IF NOT EXISTS verification_status VARCHAR(30) DEFAULT 'submitted';

-- Create missing tutor_availability_slots table
CREATE TABLE IF NOT EXISTS tutor_availability_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_user_id INT NOT NULL,
    day_of_week VARCHAR(20) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    delivery_mode VARCHAR(20) DEFAULT 'in_person',
    location_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tutor_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_tutor_availability (tutor_user_id, day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add missing columns to sessions table
ALTER TABLE sessions 
ADD COLUMN IF NOT EXISTS curriculum VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS study_level VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS preferred_date DATE NULL;

-- Verify the changes
SELECT 'students' as table_name, COUNT(*) as count FROM students
UNION ALL
SELECT 'tutors', COUNT(*) FROM tutors
UNION ALL  
SELECT 'student_profiles', COUNT(*) FROM student_profiles
UNION ALL
SELECT 'tutor_profiles', COUNT(*) FROM tutor_profiles
UNION ALL
SELECT 'tutor_availability_slots', COUNT(*) FROM tutor_availability_slots
UNION ALL
SELECT 'study_levels', COUNT(*) FROM study_levels;
