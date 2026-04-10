CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    slug VARCHAR(140) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS curricula (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    slug VARCHAR(140) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS study_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(140) NOT NULL UNIQUE,
    education_level VARCHAR(60) NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(140) NOT NULL UNIQUE,
    slug VARCHAR(160) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_profile_subjects (
    student_user_id INT NOT NULL,
    subject_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_user_id, subject_id),
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_profile_curricula (
    student_user_id INT NOT NULL,
    curriculum_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_user_id, curriculum_id),
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (curriculum_id) REFERENCES curricula(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_profile_study_levels (
    student_user_id INT NOT NULL,
    study_level_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_user_id, study_level_id),
    FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (study_level_id) REFERENCES study_levels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_profile_subjects (
    tutor_user_id INT NOT NULL,
    subject_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tutor_user_id, subject_id),
    FOREIGN KEY (tutor_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_profile_curricula (
    tutor_user_id INT NOT NULL,
    curriculum_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tutor_user_id, curriculum_id),
    FOREIGN KEY (tutor_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (curriculum_id) REFERENCES curricula(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_profile_study_levels (
    tutor_user_id INT NOT NULL,
    study_level_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tutor_user_id, study_level_id),
    FOREIGN KEY (tutor_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (study_level_id) REFERENCES study_levels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_profile_service_areas (
    tutor_user_id INT NOT NULL,
    service_area_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tutor_user_id, service_area_id),
    FOREIGN KEY (tutor_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_area_id) REFERENCES service_areas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_availability_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_user_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    delivery_mode ENUM('online', 'in_person', 'both') DEFAULT 'both',
    location_note VARCHAR(180) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tutor_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_tutor_slots (tutor_user_id, day_of_week, start_time, end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO subjects (name, slug) VALUES
    ('Biology', 'biology'),
    ('Business Studies', 'business-studies'),
    ('Chemistry', 'chemistry'),
    ('Computer Science', 'computer-science'),
    ('English', 'english'),
    ('French', 'french'),
    ('Geography', 'geography'),
    ('History', 'history'),
    ('Kiswahili', 'kiswahili'),
    ('Mathematics', 'mathematics'),
    ('Physics', 'physics')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO curricula (name, slug) VALUES
    ('8-4-4', '8-4-4'),
    ('A-Level', 'a-level'),
    ('CBC', 'cbc'),
    ('IB', 'ib'),
    ('IGCSE', 'igcse'),
    ('KCSE', 'kcse'),
    ('Professional Programme', 'professional-programme'),
    ('TVET', 'tvet'),
    ('University Degree', 'university-degree')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO study_levels (name, education_level, slug) VALUES
    ('Grade 4', 'primary', 'grade-4'),
    ('Grade 5', 'primary', 'grade-5'),
    ('Grade 6', 'primary', 'grade-6'),
    ('Grade 7', 'junior_secondary', 'grade-7'),
    ('Grade 8', 'junior_secondary', 'grade-8'),
    ('Grade 9', 'junior_secondary', 'grade-9'),
    ('Form 1', 'high_school', 'form-1'),
    ('Form 2', 'high_school', 'form-2'),
    ('Form 3', 'high_school', 'form-3'),
    ('Form 4', 'high_school', 'form-4'),
    ('Certificate Year 1', 'certificate', 'certificate-year-1'),
    ('Certificate Year 2', 'certificate', 'certificate-year-2'),
    ('Diploma Year 1', 'diploma', 'diploma-year-1'),
    ('Diploma Year 2', 'diploma', 'diploma-year-2'),
    ('Diploma Final Year', 'diploma', 'diploma-final-year'),
    ('Bachelor''s Year 1', 'bachelors', 'bachelors-year-1'),
    ('Bachelor''s Year 2', 'bachelors', 'bachelors-year-2'),
    ('Bachelor''s Year 3', 'bachelors', 'bachelors-year-3'),
    ('Bachelor''s Year 4', 'bachelors', 'bachelors-year-4'),
    ('Postgraduate Diploma', 'postgraduate_diploma', 'postgraduate-diploma'),
    ('Master''s Coursework', 'masters', 'masters-coursework'),
    ('Master''s Research', 'masters', 'masters-research'),
    ('PhD Year 1', 'phd', 'phd-year-1'),
    ('PhD Candidate', 'phd', 'phd-candidate'),
    ('CPA', 'professional', 'cpa'),
    ('ACCA', 'professional', 'acca'),
    ('Professional Certification', 'professional', 'professional-certification')
ON DUPLICATE KEY UPDATE name = VALUES(name), education_level = VALUES(education_level);

INSERT INTO service_areas (name, slug) VALUES
    ('Eldoret', 'eldoret'),
    ('Kilimani', 'kilimani'),
    ('Kisumu', 'kisumu'),
    ('Mombasa', 'mombasa'),
    ('Nairobi', 'nairobi'),
    ('Nakuru', 'nakuru'),
    ('Online', 'online'),
    ('Westlands', 'westlands')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO schema_migrations (migration_key)
VALUES ('002_phase3_product_model')
ON DUPLICATE KEY UPDATE migration_key = migration_key;
