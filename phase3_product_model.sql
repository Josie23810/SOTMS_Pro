CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS curricula (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS study_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    education_level VARCHAR(60) NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_profile_subjects (
    student_user_id INT NOT NULL,
    subject_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_user_id, subject_id),
    CONSTRAINT fk_student_profile_subjects_user FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_profile_subjects_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_profile_curricula (
    student_user_id INT NOT NULL,
    curriculum_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_user_id, curriculum_id),
    CONSTRAINT fk_student_profile_curricula_user FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_profile_curricula_curriculum FOREIGN KEY (curriculum_id) REFERENCES curricula(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_profile_study_levels (
    student_user_id INT NOT NULL,
    study_level_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_user_id, study_level_id),
    CONSTRAINT fk_student_profile_study_levels_user FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_student_profile_study_levels_level FOREIGN KEY (study_level_id) REFERENCES study_levels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_profile_subjects (
    tutor_user_id INT NOT NULL,
    subject_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tutor_user_id, subject_id),
    CONSTRAINT fk_tutor_profile_subjects_user FOREIGN KEY (tutor_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tutor_profile_subjects_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_profile_curricula (
    tutor_user_id INT NOT NULL,
    curriculum_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tutor_user_id, curriculum_id),
    CONSTRAINT fk_tutor_profile_curricula_user FOREIGN KEY (tutor_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tutor_profile_curricula_curriculum FOREIGN KEY (curriculum_id) REFERENCES curricula(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_profile_study_levels (
    tutor_user_id INT NOT NULL,
    study_level_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tutor_user_id, study_level_id),
    CONSTRAINT fk_tutor_profile_study_levels_user FOREIGN KEY (tutor_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tutor_profile_study_levels_level FOREIGN KEY (study_level_id) REFERENCES study_levels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_profile_service_areas (
    tutor_user_id INT NOT NULL,
    service_area_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tutor_user_id, service_area_id),
    CONSTRAINT fk_tutor_profile_service_areas_user FOREIGN KEY (tutor_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tutor_profile_service_areas_area FOREIGN KEY (service_area_id) REFERENCES service_areas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tutor_availability_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tutor_user_id INT NOT NULL,
    day_of_week VARCHAR(20) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    delivery_mode VARCHAR(20) DEFAULT 'both',
    location_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tutor_availability_slots_user FOREIGN KEY (tutor_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_tutor_availability_lookup (tutor_user_id, day_of_week, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE student_profiles
    ADD COLUMN IF NOT EXISTS curriculum VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS level_of_study VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS location VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS guardian_name VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS guardian_phone VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS preferred_radius_km INT DEFAULT 50,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE tutor_profiles
    ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS id_number VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS age INT NULL,
    ADD COLUMN IF NOT EXISTS curriculum_specialties TEXT NULL,
    ADD COLUMN IF NOT EXISTS study_levels_supported TEXT NULL,
    ADD COLUMN IF NOT EXISTS location VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS service_areas TEXT NULL,
    ADD COLUMN IF NOT EXISTS verification_status VARCHAR(30) DEFAULT 'submitted',
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE sessions
    ADD COLUMN IF NOT EXISTS curriculum VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS study_level VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS preferred_date DATE NULL,
    ADD COLUMN IF NOT EXISTS preferred_time TIME NULL,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE tutor_materials
    ADD COLUMN IF NOT EXISTS subject VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS curriculum VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS study_level VARCHAR(100) NULL;

INSERT IGNORE INTO subjects (name, slug) VALUES
    ('Mathematics', 'mathematics'),
    ('English', 'english'),
    ('Science', 'science'),
    ('Biology', 'biology'),
    ('Chemistry', 'chemistry'),
    ('Physics', 'physics'),
    ('History', 'history'),
    ('Geography', 'geography'),
    ('Computer Studies', 'computer-studies'),
    ('Journalism', 'journalism'),
    ('Literature', 'literature');

INSERT IGNORE INTO curricula (name, slug) VALUES
    ('CBC', 'cbc'),
    ('KCSE', 'kcse'),
    ('IGCSE', 'igcse'),
    ('IB', 'ib'),
    ('Diploma Programme', 'diploma-programme'),
    ('Bachelor''s Programme', 'bachelors-programme'),
    ('Master''s Programme', 'masters-programme'),
    ('Professional Programme', 'professional-programme');

INSERT IGNORE INTO study_levels (name, education_level, slug) VALUES
    ('Grade 1', 'primary', 'grade-1'),
    ('Grade 2', 'primary', 'grade-2'),
    ('Grade 3', 'primary', 'grade-3'),
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
    ('Certificate Level', 'certificate', 'certificate-level'),
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
    ('PhD Coursework', 'phd', 'phd-coursework'),
    ('PhD Research', 'phd', 'phd-research'),
    ('Professional Level', 'professional', 'professional-level');

INSERT IGNORE INTO service_areas (name, slug) VALUES
    ('Nairobi', 'nairobi'),
    ('Kiambu', 'kiambu'),
    ('Mombasa', 'mombasa'),
    ('Kisumu', 'kisumu'),
    ('Nakuru', 'nakuru'),
    ('Machakos', 'machakos'),
    ('Kajiado', 'kajiado'),
    ('Eldoret', 'eldoret'),
    ('Thika', 'thika'),
    ('Online', 'online');

INSERT INTO schema_migrations (migration_key)
VALUES ('002_phase3_product_model')
ON DUPLICATE KEY UPDATE migration_key = migration_key;
