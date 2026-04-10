<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/services/ProfileTaxonomyService.php';
require_once __DIR__ . '/services/TutorVerificationService.php';
require_once __DIR__ . '/services/TutorMatchService.php';

function tableExists(PDO $pdo, $tableName) {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$tableName]);
    return (bool) $stmt->fetchColumn();
}

function columnExists(PDO $pdo, $tableName, $columnName) {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$tableName` LIKE ?");
    $stmt->execute([$columnName]);
    return (bool) $stmt->fetch();
}

function platformSchemaRequirements() {
    return [
        'users' => ['id', 'name', 'email', 'password', 'role', 'created_at'],
        'user_sessions' => ['id', 'user_id', 'role', 'session_token', 'browser_fingerprint', 'ip_address', 'created_at', 'last_activity', 'expires_at'],
        'students' => ['id', 'user_id', 'created_at'],
        'tutors' => ['id', 'user_id', 'created_at'],
        'subjects' => ['id', 'name', 'slug', 'created_at'],
        'curricula' => ['id', 'name', 'slug', 'created_at'],
        'study_levels' => ['id', 'name', 'education_level', 'slug', 'created_at'],
        'service_areas' => ['id', 'name', 'slug', 'created_at'],
        'student_profiles' => ['id', 'user_id', 'profile_image', 'full_name', 'phone', 'education_level', 'current_institution', 'subjects_interested', 'bio', 'goals', 'curriculum', 'level_of_study', 'location', 'guardian_name', 'guardian_phone', 'preferred_radius_km', 'created_at', 'updated_at'],
        'tutor_profiles' => ['id', 'user_id', 'profile_image', 'full_name', 'phone', 'email', 'id_number', 'age', 'subjects_taught', 'curriculum_specialties', 'study_levels_supported', 'qualifications', 'qualification_document', 'bio', 'experience', 'hourly_rate', 'location', 'service_areas', 'availability_days', 'availability_start', 'availability_end', 'max_sessions_per_day', 'verification_status', 'created_at', 'updated_at'],
        'student_profile_subjects' => ['student_user_id', 'subject_id', 'created_at'],
        'student_profile_curricula' => ['student_user_id', 'curriculum_id', 'created_at'],
        'student_profile_study_levels' => ['student_user_id', 'study_level_id', 'created_at'],
        'tutor_profile_subjects' => ['tutor_user_id', 'subject_id', 'created_at'],
        'tutor_profile_curricula' => ['tutor_user_id', 'curriculum_id', 'created_at'],
        'tutor_profile_study_levels' => ['tutor_user_id', 'study_level_id', 'created_at'],
        'tutor_profile_service_areas' => ['tutor_user_id', 'service_area_id', 'created_at'],
        'tutor_availability_slots' => ['id', 'tutor_user_id', 'day_of_week', 'start_time', 'end_time', 'delivery_mode', 'location_note', 'created_at', 'updated_at'],
        'payment_events' => ['id', 'payment_id', 'event_type', 'event_note', 'event_data', 'created_by', 'created_at'],
        'tutor_verification_reviews' => ['id', 'tutor_user_id', 'admin_user_id', 'decision', 'review_notes', 'created_at'],
        'messages' => ['id', 'sender_id', 'receiver_id', 'subject', 'message', 'is_read', 'created_at'],
        'sessions' => ['id', 'student_id', 'tutor_id', 'subject', 'curriculum', 'study_level', 'session_date', 'preferred_date', 'preferred_time', 'duration', 'notes', 'meeting_link', 'status', 'payment_status', 'amount', 'payment_amount', 'created_at', 'updated_at'],
        'tutor_materials' => ['id', 'tutor_id', 'title', 'subject', 'curriculum', 'study_level', 'description', 'file_path', 'file_name', 'uploaded_at'],
        'payments' => ['id', 'session_id', 'student_id', 'tutor_id', 'amount', 'currency', 'provider', 'tracking_id', 'reference', 'status', 'pesapal_txn_id', 'paypal_payment_id', 'payment_data', 'created_at', 'updated_at'],
    ];
}

function missingPlatformRequirements(PDO $pdo) {
    $missing = [];
    foreach (platformSchemaRequirements() as $table => $columns) {
        if (!tableExists($pdo, $table)) {
            $missing[] = 'table:' . $table;
            continue;
        }

        foreach ($columns as $column) {
            if (!columnExists($pdo, $table, $column)) {
                $missing[] = 'column:' . $table . '.' . $column;
            }
        }
    }

    return $missing;
}

function ensurePlatformStructures(PDO $pdo) {
    static $validated = false;
    if ($validated) {
        return;
    }

    $missing = missingPlatformRequirements($pdo);
    if (!empty($missing)) {
        http_response_code(500);
        die(
            'Platform schema is not ready. Run run_phase1_migration.php, run_phase3_migration.php, and run_phase4_migration.php before using the application. Missing: '
            . htmlspecialchars(implode(', ', array_slice($missing, 0, 20)))
        );
    }

    $validated = true;
}

function normalizeCsvArray($value) {
    if (is_array($value)) {
        $items = $value;
    } else {
        $items = preg_split('/[\r\n,]+/', (string) $value);
    }

    $normalized = [];
    foreach ($items as $item) {
        $item = trim((string) $item);
        if ($item === '') {
            continue;
        }
        $key = strtolower($item);
        $normalized[$key] = $item;
    }

    return array_values($normalized);
}

function csvContains($csvValue, $needle) {
    $needle = strtolower(trim((string) $needle));
    if ($needle === '') {
        return false;
    }

    foreach (normalizeCsvArray($csvValue) as $item) {
        if (strtolower($item) === $needle) {
            return true;
        }
    }

    return false;
}

function tokenizeLocation($value) {
    $parts = preg_split('/[\s,\/-]+/', strtolower((string) $value));
    $tokens = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '' && strlen($part) > 2) {
            $tokens[$part] = true;
        }
    }
    return array_keys($tokens);
}

function matchLocationScore($studentLocation, $tutorLocation, $serviceAreas = '') {
    $studentTokens = tokenizeLocation($studentLocation);
    if (empty($studentTokens)) {
        return [0, null];
    }

    $allTutorTokens = array_merge(tokenizeLocation($tutorLocation), tokenizeLocation($serviceAreas));
    $lookup = array_flip($allTutorTokens);

    foreach ($studentTokens as $token) {
        if (isset($lookup[$token])) {
            return [2, ucwords($token)];
        }
    }

    return [0, null];
}

function parseRateAmount($rate) {
    if ($rate === null || $rate === '') {
        return 500.00;
    }

    if (is_numeric($rate)) {
        return (float) $rate;
    }

    if (preg_match('/(\d+(?:\.\d+)?)/', (string) $rate, $matches)) {
        return (float) $matches[1];
    }

    return 500.00;
}

function getStudentRecord(PDO $pdo, $userId) {
    ensurePlatformStructures($pdo);

    $stmt = $pdo->prepare('SELECT id, user_id FROM students WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        return $student;
    }

    $stmt = $pdo->prepare('INSERT INTO students (id, user_id) VALUES (?, ?)');
    $stmt->execute([$userId, $userId]);
    return ['id' => $userId, 'user_id' => $userId];
}

function getTutorRecord(PDO $pdo, $userId) {
    ensurePlatformStructures($pdo);

    $stmt = $pdo->prepare('SELECT id, user_id FROM tutors WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $tutor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tutor) {
        return $tutor;
    }

    $stmt = $pdo->prepare('INSERT INTO tutors (id, user_id) VALUES (?, ?)');
    $stmt->execute([$userId, $userId]);
    return ['id' => $userId, 'user_id' => $userId];
}

function getStudentId(PDO $pdo, $userId) {
    $student = getStudentRecord($pdo, $userId);
    return $student['id'] ?? null;
}

function getTutorId(PDO $pdo, $userId) {
    $tutor = getTutorRecord($pdo, $userId);
    return $tutor['id'] ?? null;
}

function getAvailableTutors(PDO $pdo) {
    ensurePlatformStructures($pdo);

    $stmt = $pdo->prepare('
        SELECT t.id, u.name
        FROM tutors t
        JOIN users u ON t.user_id = u.id
        ORDER BY u.name
    ');
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ensureSessionStructure(PDO $pdo) {
    ensurePlatformStructures($pdo);
}

function fetchStudentProfile(PDO $pdo, $userId) {
    ensurePlatformStructures($pdo);

    $stmt = $pdo->prepare('SELECT * FROM student_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return ProfileTaxonomyService::enrichStudentProfile($pdo, $profile, $userId);
}

function fetchTutorProfile(PDO $pdo, $userId) {
    ensurePlatformStructures($pdo);

    $stmt = $pdo->prepare('SELECT * FROM tutor_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return ProfileTaxonomyService::enrichTutorProfile($pdo, $profile, $userId);
}

function fetchTutorMatches(PDO $pdo, $studentUserId, $limit = null) {
    return TutorMatchService::getMatches($pdo, $studentUserId, $limit);
}

function hasScheduleCollision(PDO $pdo, $sessionDate, $duration, $actorColumn, $actorId, $excludeSessionId = null) {
    ensurePlatformStructures($pdo);

    if (!$actorId || empty($sessionDate) || $duration <= 0) {
        return false;
    }

    $sql = "
        SELECT id
        FROM sessions
        WHERE $actorColumn = ?
          AND status IN ('pending', 'confirmed')
          AND session_date < DATE_ADD(?, INTERVAL ? MINUTE)
          AND DATE_ADD(session_date, INTERVAL duration MINUTE) > ?
    ";

    $params = [$actorId, $sessionDate, $duration, $sessionDate];

    if ($excludeSessionId) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeSessionId;
    }

    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

function hasTutorScheduleCollision(PDO $pdo, $tutorId, $sessionDate, $duration, $excludeSessionId = null) {
    return hasScheduleCollision($pdo, $sessionDate, $duration, 'tutor_id', $tutorId, $excludeSessionId);
}

function hasStudentScheduleCollision(PDO $pdo, $studentId, $sessionDate, $duration, $excludeSessionId = null) {
    return hasScheduleCollision($pdo, $sessionDate, $duration, 'student_id', $studentId, $excludeSessionId);
}

function countTutorSessionsForDate(PDO $pdo, $tutorId, $date, $excludeSessionId = null) {
    ensurePlatformStructures($pdo);

    $sql = "
        SELECT COUNT(*)
        FROM sessions
        WHERE tutor_id = ?
          AND DATE(session_date) = ?
          AND status IN ('pending', 'confirmed')
    ";
    $params = [$tutorId, $date];

    if ($excludeSessionId) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeSessionId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function fetchMessagesForUser(PDO $pdo, $userId, $type = 'received', $limit = 20) {
    ensurePlatformStructures($pdo);

    if ($type === 'received') {
        $sql = 'SELECT m.*, u.name AS sender_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = ? ORDER BY m.created_at DESC LIMIT ?';
    } else {
        $sql = 'SELECT m.*, u.name AS receiver_name FROM messages m JOIN users u ON m.receiver_id = u.id WHERE m.sender_id = ? ORDER BY m.created_at DESC LIMIT ?';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markMessagesRead(PDO $pdo, $userId) {
    ensurePlatformStructures($pdo);

    $stmt = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
}
?>
