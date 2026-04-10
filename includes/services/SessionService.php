<?php

class SessionService {
    public static function fetchTutorDirectory(PDO $pdo) {
        ensurePlatformStructures($pdo);

        $stmt = $pdo->prepare('
            SELECT
                t.id AS tutor_id,
                t.user_id AS tutor_user_id,
                u.name,
                u.email,
                tp.*
            FROM tutors t
            JOIN users u ON t.user_id = u.id
            LEFT JOIN tutor_profiles tp ON tp.user_id = u.id
            ORDER BY u.name
        ');
        $stmt->execute();

        $tutors = ProfileTaxonomyService::attachTutorSelectionsToRows($pdo, $stmt->fetchAll(PDO::FETCH_ASSOC));
        foreach ($tutors as &$tutor) {
            $tutor['id'] = (int) ($tutor['tutor_id'] ?? 0);
            $tutor['session_rate'] = parseRateAmount($tutor['hourly_rate'] ?? null);
        }
        unset($tutor);

        return $tutors;
    }

    public static function mapTutorsById(array $tutors) {
        $mapped = [];
        foreach ($tutors as $tutor) {
            $mapped[(int) $tutor['id']] = $tutor;
        }

        return $mapped;
    }

    public static function findStudentSession(PDO $pdo, $sessionId, $studentId) {
        ensurePlatformStructures($pdo);

        $stmt = $pdo->prepare('SELECT * FROM sessions WHERE id = ? AND student_id = ? LIMIT 1');
        $stmt->execute([$sessionId, $studentId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findTutorSession(PDO $pdo, $sessionId, $tutorId) {
        ensurePlatformStructures($pdo);

        $stmt = $pdo->prepare('SELECT * FROM sessions WHERE id = ? AND tutor_id = ? LIMIT 1');
        $stmt->execute([$sessionId, $tutorId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function fetchTutorScheduleSessions(PDO $pdo, $tutorId) {
        ensurePlatformStructures($pdo);

        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.session_date,
                s.status,
                s.subject,
                s.curriculum,
                s.study_level,
                s.duration,
                s.notes,
                s.meeting_link,
                s.payment_status,
                u.name AS student_name
            FROM sessions s
            LEFT JOIN students st ON s.student_id = st.id
            LEFT JOIN users u ON st.user_id = u.id
            WHERE s.tutor_id = ?
            ORDER BY
                CASE
                    WHEN s.status = 'pending' THEN 1
                    WHEN s.status = 'confirmed' THEN 2
                    WHEN s.status = 'completed' THEN 3
                    ELSE 4
                END,
                s.session_date ASC
        ");
        $stmt->execute([$tutorId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fetchStudentName(PDO $pdo, $studentId) {
        ensurePlatformStructures($pdo);

        $stmt = $pdo->prepare('SELECT u.name FROM students st JOIN users u ON st.user_id = u.id WHERE st.id = ? LIMIT 1');
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        return $student['name'] ?? 'Unknown Student';
    }

    public static function validateStudentBookingRequest(PDO $pdo, $studentId, $selectedTutor, array $input, $excludeSessionId = null) {
        $data = self::normalizeSessionInput($input);
        $errors = self::validateCoreInput($data, true);

        if (!$selectedTutor && !empty($data['tutor_id'])) {
            $errors[] = 'Please select a valid tutor.';
        }

        if ($data['session_date'] && strtotime($data['session_date']) <= time()) {
            $errors[] = 'Session time must be in the future.';
        }

        if ($selectedTutor && $data['session_date']) {
            self::validateTutorAvailability($pdo, $selectedTutor, $studentId, $data, $errors, $excludeSessionId);
        }

        return [
            'errors' => $errors,
            'data' => $data,
        ];
    }

    public static function createStudentBooking(PDO $pdo, $studentId, array $studentProfile, array $selectedTutor, array $data) {
        $amount = parseRateAmount($selectedTutor['hourly_rate'] ?? null);
        $curriculum = trim((string) ($studentProfile['curriculum'] ?? ''));
        $studyLevel = trim((string) ($studentProfile['level_of_study'] ?? ($studentProfile['education_level'] ?? '')));

        $stmt = $pdo->prepare('
            INSERT INTO sessions (
                student_id, tutor_id, subject, curriculum, study_level, session_date, preferred_date, preferred_time,
                duration, notes, status, payment_status, amount, payment_amount
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $studentId,
            $selectedTutor['id'],
            $data['subject'],
            $curriculum,
            $studyLevel,
            $data['session_date'],
            $data['date'],
            $data['time'],
            $data['duration'],
            $data['notes'],
            'pending',
            'unpaid',
            $amount,
            $amount,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function updateStudentBooking(PDO $pdo, $sessionId, $studentId, array $selectedTutor, array $data) {
        $amount = parseRateAmount($selectedTutor['hourly_rate'] ?? null);

        $stmt = $pdo->prepare('
            UPDATE sessions
            SET subject = ?, tutor_id = ?, session_date = ?, preferred_date = ?, preferred_time = ?, duration = ?, notes = ?, amount = ?, payment_amount = ?
            WHERE id = ? AND student_id = ?
        ');
        $stmt->execute([
            $data['subject'],
            $selectedTutor['id'],
            $data['session_date'],
            $data['date'],
            $data['time'],
            $data['duration'],
            $data['notes'],
            $amount,
            $amount,
            $sessionId,
            $studentId,
        ]);
    }

    public static function validateTutorManagedUpdate(PDO $pdo, $tutorId, $studentId, array $input, $excludeSessionId = null) {
        $data = self::normalizeSessionInput($input);
        $errors = self::validateCoreInput($data, false);

        if ($data['session_date'] && strtotime($data['session_date']) <= time()) {
            $errors[] = 'Session time must be in the future.';
        }

        if ($data['session_date']) {
            if (hasTutorScheduleCollision($pdo, $tutorId, $data['session_date'], $data['duration'], $excludeSessionId)) {
                $errors[] = 'This update collides with another session already in your schedule.';
            }

            if ($studentId && hasStudentScheduleCollision($pdo, $studentId, $data['session_date'], $data['duration'], $excludeSessionId)) {
                $errors[] = 'The student already has another session at that time.';
            }
        }

        return [
            'errors' => $errors,
            'data' => $data,
        ];
    }

    public static function updateTutorManagedSession(PDO $pdo, $sessionId, $tutorId, array $data) {
        $stmt = $pdo->prepare('
            UPDATE sessions
            SET subject = ?, session_date = ?, preferred_date = ?, preferred_time = ?, duration = ?, notes = ?, meeting_link = ?
            WHERE id = ? AND tutor_id = ?
        ');
        $stmt->execute([
            $data['subject'],
            $data['session_date'],
            $data['date'],
            $data['time'],
            $data['duration'],
            $data['notes'],
            $data['meeting_link'],
            $sessionId,
            $tutorId,
        ]);
    }

    public static function applyTutorScheduleAction(PDO $pdo, $sessionId, $tutorId, $action) {
        $session = self::findTutorSession($pdo, $sessionId, $tutorId);
        if (!$session) {
            return ['type' => 'error', 'message' => 'Session not found or you do not have permission to modify it.'];
        }

        if ($action === 'accept') {
            if (hasTutorScheduleCollision($pdo, $tutorId, $session['session_date'], (int) $session['duration'], $sessionId)) {
                return ['type' => 'error', 'message' => 'You cannot confirm this request because it collides with another session in your schedule.'];
            }

            $stmt = $pdo->prepare('UPDATE sessions SET status = ? WHERE id = ? AND tutor_id = ?');
            $stmt->execute(['confirmed', $sessionId, $tutorId]);

            return ['type' => 'success', 'message' => 'Session accepted successfully.'];
        }

        if ($action === 'decline') {
            $stmt = $pdo->prepare('UPDATE sessions SET status = ? WHERE id = ? AND tutor_id = ?');
            $stmt->execute(['cancelled', $sessionId, $tutorId]);

            return ['type' => 'success', 'message' => 'Session declined.'];
        }

        return ['type' => 'error', 'message' => 'Unsupported schedule action.'];
    }

    private static function normalizeSessionInput(array $input) {
        $date = trim((string) ($input['date'] ?? ''));
        $time = trim((string) ($input['time'] ?? ''));
        $timestamp = ($date !== '' && $time !== '') ? strtotime($date . ' ' . $time) : false;

        return [
            'subject' => trim((string) ($input['subject'] ?? '')),
            'tutor_id' => (int) ($input['tutor_id'] ?? 0),
            'date' => $date,
            'time' => $time,
            'duration' => (int) ($input['duration'] ?? 60),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'meeting_link' => trim((string) ($input['meeting_link'] ?? '')),
            'session_date' => $timestamp ? date('Y-m-d H:i:s', $timestamp) : null,
        ];
    }

    private static function validateCoreInput(array $data, $requiresTutor) {
        $errors = [];

        if ($requiresTutor && empty($data['tutor_id'])) {
            $errors[] = 'Please select a tutor.';
        }
        if ($data['subject'] === '') {
            $errors[] = 'Subject is required.';
        }
        if ($data['date'] === '') {
            $errors[] = 'Date is required.';
        }
        if ($data['time'] === '') {
            $errors[] = 'Time is required.';
        }
        if ($data['date'] !== '' && $data['time'] !== '' && !$data['session_date']) {
            $errors[] = 'Choose a valid date and time.';
        }
        if (!in_array($data['duration'], [60, 90, 120], true)) {
            $errors[] = 'Choose a valid duration.';
        }

        return $errors;
    }

    private static function validateTutorAvailability(PDO $pdo, array $selectedTutor, $studentId, array $data, array &$errors, $excludeSessionId = null) {
        if (!self::fitsTutorAvailability($selectedTutor, $data['session_date'], $data['duration'], $data['date'])) {
            $errors[] = 'Choose a time within one of the tutor availability slots.';
        }

        if (hasTutorScheduleCollision($pdo, $selectedTutor['id'], $data['session_date'], $data['duration'], $excludeSessionId)) {
            $errors[] = 'This tutor already has another session at that time.';
        }

        if (hasStudentScheduleCollision($pdo, $studentId, $data['session_date'], $data['duration'], $excludeSessionId)) {
            $errors[] = 'You already have another session at that time.';
        }

        if (!empty($selectedTutor['max_sessions_per_day'])) {
            $dailyCount = countTutorSessionsForDate($pdo, $selectedTutor['id'], $data['date'], $excludeSessionId);
            if ($dailyCount >= (int) $selectedTutor['max_sessions_per_day']) {
                $errors[] = 'This tutor has reached their maximum sessions for that day.';
            }
        }
    }

    private static function fitsTutorAvailability(array $selectedTutor, $sessionDate, $duration, $date) {
        $slots = $selectedTutor['availability_slots'] ?? [];
        if (!empty($slots)) {
            $dayName = date('l', strtotime($sessionDate));
            $sessionStart = strtotime($sessionDate);
            $sessionEnd = strtotime('+' . $duration . ' minutes', $sessionStart);

            foreach ($slots as $slot) {
                if (($slot['day_of_week'] ?? '') !== $dayName) {
                    continue;
                }

                $windowStart = strtotime($date . ' ' . ($slot['start_time'] ?? ''));
                $windowEnd = strtotime($date . ' ' . ($slot['end_time'] ?? ''));

                if ($windowStart !== false && $windowEnd !== false && $sessionStart >= $windowStart && $sessionEnd <= $windowEnd) {
                    return true;
                }
            }

            return false;
        }

        $availabilityDays = normalizeCsvArray($selectedTutor['availability_days'] ?? '');
        $dayName = date('l', strtotime($sessionDate));
        if (!empty($availabilityDays) && !in_array($dayName, $availabilityDays, true)) {
            return false;
        }

        if (!empty($selectedTutor['availability_start']) && !empty($selectedTutor['availability_end'])) {
            $sessionStart = strtotime($sessionDate);
            $sessionEnd = strtotime('+' . $duration . ' minutes', $sessionStart);
            $windowStart = strtotime($date . ' ' . $selectedTutor['availability_start']);
            $windowEnd = strtotime($date . ' ' . $selectedTutor['availability_end']);

            return $sessionStart >= $windowStart && $sessionEnd <= $windowEnd;
        }

        return true;
    }
}
