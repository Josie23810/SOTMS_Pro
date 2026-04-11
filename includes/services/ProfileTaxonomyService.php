<?php

class ProfileTaxonomyService {
    private static $educationLevelLabels = [
        'primary' => 'Primary School',
        'junior_secondary' => 'Junior Secondary',
        'high_school' => 'Senior Secondary / High School',
        'certificate' => 'Certificate',
        'diploma' => 'Diploma',
        'bachelors' => "Bachelor's Degree",
        'postgraduate_diploma' => 'Postgraduate Diploma',
        'masters' => "Master's Degree",
        'phd' => 'PhD / Doctorate',
        'professional' => 'Professional Qualification',
        'other' => 'Other',
    ];

    public static function getCatalogOptions(PDO $pdo) {
        ensurePlatformStructures($pdo);

        return [
            'subjects' => self::fetchLookupNames($pdo, 'subjects'),
            'curricula' => self::fetchLookupNames($pdo, 'curricula'),
            'study_levels' => self::fetchLookupNames($pdo, 'study_levels'),
            'service_areas' => self::fetchLookupNames($pdo, 'service_areas'),
        ];
    }

    public static function syncStudentProfile(PDO $pdo, $userId, array $profileData) {
        ensurePlatformStructures($pdo);

        self::syncLookupMappings(
            $pdo,
            'subjects',
            'student_profile_subjects',
            'student_user_id',
            'subject_id',
            $userId,
            normalizeCsvArray($profileData['subjects_interested'] ?? '')
        );

        self::syncLookupMappings(
            $pdo,
            'curricula',
            'student_profile_curricula',
            'student_user_id',
            'curriculum_id',
            $userId,
            normalizeCsvArray($profileData['curriculum'] ?? '')
        );

        self::syncLookupMappings(
            $pdo,
            'study_levels',
            'student_profile_study_levels',
            'student_user_id',
            'study_level_id',
            $userId,
            normalizeCsvArray($profileData['level_of_study'] ?? ''),
            ['education_level' => $profileData['education_level'] ?? null]
        );
    }

    public static function syncTutorProfile(PDO $pdo, $userId, array $profileData) {
        ensurePlatformStructures($pdo);

        self::syncLookupMappings(
            $pdo,
            'subjects',
            'tutor_profile_subjects',
            'tutor_user_id',
            'subject_id',
            $userId,
            normalizeCsvArray($profileData['subjects_taught'] ?? '')
        );

        self::syncLookupMappings(
            $pdo,
            'curricula',
            'tutor_profile_curricula',
            'tutor_user_id',
            'curriculum_id',
            $userId,
            normalizeCsvArray($profileData['curriculum_specialties'] ?? '')
        );

        self::syncLookupMappings(
            $pdo,
            'study_levels',
            'tutor_profile_study_levels',
            'tutor_user_id',
            'study_level_id',
            $userId,
            normalizeCsvArray($profileData['study_levels_supported'] ?? '')
        );

        self::syncLookupMappings(
            $pdo,
            'service_areas',
            'tutor_profile_service_areas',
            'tutor_user_id',
            'service_area_id',
            $userId,
            normalizeCsvArray($profileData['service_areas'] ?? '')
        );

        $slots = $profileData['availability_slots'] ?? self::expandAvailabilitySlots(
            $profileData['availability_days'] ?? [],
            $profileData['availability_start'] ?? '',
            $profileData['availability_end'] ?? '',
            $profileData['delivery_mode'] ?? 'both',
            $profileData['location_note'] ?? ''
        );
        self::replaceTutorAvailabilitySlots($pdo, $userId, $slots);
    }

    public static function enrichStudentProfile(PDO $pdo, $profile, $userId) {
        if (!$profile) {
            return null;
        }

        $profile['subject_names'] = self::fetchMappedNames($pdo, 'student_profile_subjects', 'student_user_id', 'subject_id', 'subjects', [$userId])[$userId] ?? [];
        $profile['curriculum_names'] = self::fetchMappedNames($pdo, 'student_profile_curricula', 'student_user_id', 'curriculum_id', 'curricula', [$userId])[$userId] ?? [];
        $profile['study_level_names'] = self::fetchMappedNames($pdo, 'student_profile_study_levels', 'student_user_id', 'study_level_id', 'study_levels', [$userId])[$userId] ?? [];

        return self::applyDisplayFields($profile, 'student');
    }

    public static function enrichTutorProfile(PDO $pdo, $profile, $userId) {
        if (!$profile) {
            return null;
        }

        $profile['subject_names'] = self::fetchMappedNames($pdo, 'tutor_profile_subjects', 'tutor_user_id', 'subject_id', 'subjects', [$userId])[$userId] ?? [];
        $profile['curriculum_names'] = self::fetchMappedNames($pdo, 'tutor_profile_curricula', 'tutor_user_id', 'curriculum_id', 'curricula', [$userId])[$userId] ?? [];
        $profile['study_level_names'] = self::fetchMappedNames($pdo, 'tutor_profile_study_levels', 'tutor_user_id', 'study_level_id', 'study_levels', [$userId])[$userId] ?? [];
        $profile['service_area_names'] = self::fetchMappedNames($pdo, 'tutor_profile_service_areas', 'tutor_user_id', 'service_area_id', 'service_areas', [$userId])[$userId] ?? [];
        $profile['availability_slots'] = self::fetchAvailabilitySlots($pdo, [$userId])[$userId] ?? [];

        return self::applyDisplayFields($profile, 'tutor');
    }

    public static function attachTutorSelectionsToRows(PDO $pdo, array $rows) {
        if (empty($rows)) {
            return $rows;
        }

        $ownerIds = [];
        foreach ($rows as $row) {
            $ownerId = (int) ($row['user_id'] ?? $row['tutor_user_id'] ?? 0);
            if ($ownerId > 0) {
                $ownerIds[$ownerId] = $ownerId;
            }
        }

        $ownerIds = array_values($ownerIds);
        if (empty($ownerIds)) {
            return $rows;
        }

        $subjects = self::fetchMappedNames($pdo, 'tutor_profile_subjects', 'tutor_user_id', 'subject_id', 'subjects', $ownerIds);
        $curricula = self::fetchMappedNames($pdo, 'tutor_profile_curricula', 'tutor_user_id', 'curriculum_id', 'curricula', $ownerIds);
        $studyLevels = self::fetchMappedNames($pdo, 'tutor_profile_study_levels', 'tutor_user_id', 'study_level_id', 'study_levels', $ownerIds);
        $serviceAreas = self::fetchMappedNames($pdo, 'tutor_profile_service_areas', 'tutor_user_id', 'service_area_id', 'service_areas', $ownerIds);
        $slots = self::fetchAvailabilitySlots($pdo, $ownerIds);

        foreach ($rows as &$row) {
            $ownerId = (int) ($row['user_id'] ?? $row['tutor_user_id'] ?? 0);
            $row['subject_names'] = $subjects[$ownerId] ?? [];
            $row['curriculum_names'] = $curricula[$ownerId] ?? [];
            $row['study_level_names'] = $studyLevels[$ownerId] ?? [];
            $row['service_area_names'] = $serviceAreas[$ownerId] ?? [];
            $row['availability_slots'] = $slots[$ownerId] ?? [];
            $row = self::applyDisplayFields($row, 'tutor');
        }
        unset($row);

        return $rows;
    }

    public static function expandAvailabilitySlots(array $days, $start, $end, $deliveryMode = 'both', $locationNote = '') {
        $slots = [];
        $deliveryMode = trim((string) $deliveryMode) ?: 'both';
        $locationNote = trim((string) $locationNote);

        foreach (normalizeCsvArray($days) as $day) {
            $normalizedDay = self::normalizeDayName($day);
            if ($normalizedDay === null) {
                continue;
            }

            if (!self::isValidTimeRange($start, $end)) {
                continue;
            }

            $slots[] = [
                'day_of_week' => $normalizedDay,
                'start_time' => $start,
                'end_time' => $end,
                'delivery_mode' => in_array($deliveryMode, ['online', 'in_person', 'both'], true) ? $deliveryMode : 'both',
                'location_note' => $locationNote,
            ];
        }

        return $slots;
    }

    public static function summarizeAvailabilitySlots(array $slots) {
        if (empty($slots)) {
            return '';
        }

        $parts = [];
        foreach ($slots as $slot) {
            $start = self::formatTimeLabel($slot['start_time'] ?? '');
            $end = self::formatTimeLabel($slot['end_time'] ?? '');
            $part = trim(($slot['day_of_week'] ?? '') . ' ' . $start . '-' . $end);

            $deliveryMode = trim((string) ($slot['delivery_mode'] ?? ''));
            if ($deliveryMode !== '' && $deliveryMode !== 'both') {
                $part .= ' (' . self::labelDeliveryMode($deliveryMode) . ')';
            }

            $locationNote = trim((string) ($slot['location_note'] ?? ''));
            if ($locationNote !== '') {
                $part .= ' - ' . $locationNote;
            }

            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode(', ', $parts);
    }

    private static function applyDisplayFields(array $profile, $type) {
        if ($type === 'student') {
            $profile['education_level_display'] = self::labelEducationLevel($profile['education_level'] ?? '');
            $profile['subjects_display'] = !empty($profile['subject_names']) ? implode(', ', $profile['subject_names']) : (string) ($profile['subjects_interested'] ?? '');
            $profile['curriculum_display'] = !empty($profile['curriculum_names']) ? implode(', ', $profile['curriculum_names']) : (string) ($profile['curriculum'] ?? '');
            $profile['study_level_display'] = !empty($profile['study_level_names']) ? implode(', ', $profile['study_level_names']) : (string) ($profile['level_of_study'] ?? ($profile['education_level_display'] ?? ''));
        } else {
            $profile['subjects_taught_display'] = !empty($profile['subject_names']) ? implode(', ', $profile['subject_names']) : (string) ($profile['subjects_taught'] ?? '');
            $profile['curriculum_specialties_display'] = !empty($profile['curriculum_names']) ? implode(', ', $profile['curriculum_names']) : (string) ($profile['curriculum_specialties'] ?? '');
            $profile['study_levels_supported_display'] = !empty($profile['study_level_names']) ? implode(', ', $profile['study_level_names']) : (string) ($profile['study_levels_supported'] ?? '');
            $profile['service_areas_display'] = !empty($profile['service_area_names']) ? implode(', ', $profile['service_area_names']) : (string) ($profile['service_areas'] ?? '');
            $profile['availability_summary'] = self::summarizeAvailabilitySlots($profile['availability_slots'] ?? []);
        }

        return $profile;
    }

    public static function labelEducationLevel($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $legacyMap = [
            'college' => "Bachelor's Degree",
            'university' => "Bachelor's Degree",
            'tertiary' => "Bachelor's Degree",
            'secondary' => 'Senior Secondary / High School',
            'junior secondary' => 'Junior Secondary',
        ];

        $legacyKey = strtolower($value);
        if (isset($legacyMap[$legacyKey])) {
            return $legacyMap[$legacyKey];
        }

        return self::$educationLevelLabels[$value] ?? ucwords(str_replace('_', ' ', $value));
    }

    private static function fetchLookupNames(PDO $pdo, $table) {
        $stmt = $pdo->query("SELECT name FROM {$table} ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private static function syncLookupMappings(PDO $pdo, $lookupTable, $mappingTable, $ownerColumn, $lookupColumn, $ownerId, array $values, array $lookupDefaults = []) {
        $values = normalizeCsvArray($values);
        $lookupIds = self::ensureLookupIds($pdo, $lookupTable, $values, $lookupDefaults);

        $deleteStmt = $pdo->prepare("DELETE FROM {$mappingTable} WHERE {$ownerColumn} = ?");
        $deleteStmt->execute([$ownerId]);

        if (empty($lookupIds)) {
            return;
        }

        $insertStmt = $pdo->prepare("INSERT INTO {$mappingTable} ({$ownerColumn}, {$lookupColumn}) VALUES (?, ?)");
        foreach ($lookupIds as $lookupId) {
            $insertStmt->execute([$ownerId, $lookupId]);
        }
    }

    private static function ensureLookupIds(PDO $pdo, $lookupTable, array $values, array $defaults = []) {
        $lookupIds = [];
        $selectStmt = $pdo->prepare("SELECT id FROM {$lookupTable} WHERE LOWER(name) = LOWER(?) LIMIT 1");

        $insertSql = "INSERT INTO {$lookupTable} (name, slug";
        $insertParams = ['name', 'slug'];
        if ($lookupTable === 'study_levels') {
            $insertSql .= ', education_level';
            $insertParams[] = 'education_level';
        }
        $insertSql .= ') VALUES (' . implode(', ', array_fill(0, count($insertParams), '?')) . ')';
        $insertStmt = $pdo->prepare($insertSql);

        foreach ($values as $value) {
            $selectStmt->execute([$value]);
            $existingId = $selectStmt->fetchColumn();
            if ($existingId) {
                $lookupIds[] = (int) $existingId;
                continue;
            }

            $params = [$value, self::slugify($value)];
            if ($lookupTable === 'study_levels') {
                $params[] = $defaults['education_level'] ?? null;
            }

            $insertStmt->execute($params);
            $lookupIds[] = (int) $pdo->lastInsertId();
        }

        return array_values(array_unique($lookupIds));
    }

    private static function fetchMappedNames(PDO $pdo, $mappingTable, $ownerColumn, $lookupColumn, $lookupTable, array $ownerIds) {
        if (empty($ownerIds)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ownerIds), '?'));
        $sql = "
            SELECT m.{$ownerColumn} AS owner_id, l.name
            FROM {$mappingTable} m
            JOIN {$lookupTable} l ON l.id = m.{$lookupColumn}
            WHERE m.{$ownerColumn} IN ({$placeholders})
            ORDER BY l.name ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ownerIds);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ownerId = (int) $row['owner_id'];
            $grouped[$ownerId][] = $row['name'];
        }

        return $grouped;
    }

    private static function fetchAvailabilitySlots(PDO $pdo, array $ownerIds) {
        if (empty($ownerIds)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ownerIds), '?'));
        $stmt = $pdo->prepare("
            SELECT tutor_user_id, day_of_week, start_time, end_time, delivery_mode, location_note
            FROM tutor_availability_slots
            WHERE tutor_user_id IN ({$placeholders})
            ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time
        ");
        $stmt->execute($ownerIds);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ownerId = (int) $row['tutor_user_id'];
            $grouped[$ownerId][] = $row;
        }

        return $grouped;
    }

    private static function replaceTutorAvailabilitySlots(PDO $pdo, $userId, array $slots) {
        $pdo->prepare('DELETE FROM tutor_availability_slots WHERE tutor_user_id = ?')->execute([$userId]);

        if (empty($slots)) {
            return;
        }

        $insertStmt = $pdo->prepare('
            INSERT INTO tutor_availability_slots (tutor_user_id, day_of_week, start_time, end_time, delivery_mode, location_note)
            VALUES (?, ?, ?, ?, ?, ?)
        ');

        $seen = [];
        foreach ($slots as $slot) {
            $day = self::normalizeDayName($slot['day_of_week'] ?? '');
            $start = trim((string) ($slot['start_time'] ?? ''));
            $end = trim((string) ($slot['end_time'] ?? ''));
            $deliveryMode = trim((string) ($slot['delivery_mode'] ?? 'both'));
            $locationNote = trim((string) ($slot['location_note'] ?? ''));

            if ($day === null || !self::isValidTimeRange($start, $end)) {
                continue;
            }

            if (!in_array($deliveryMode, ['online', 'in_person', 'both'], true)) {
                $deliveryMode = 'both';
            }

            $signature = implode('|', [$day, $start, $end, $deliveryMode, $locationNote]);
            if (isset($seen[$signature])) {
                continue;
            }
            $seen[$signature] = true;

            $insertStmt->execute([$userId, $day, $start, $end, $deliveryMode, $locationNote]);
        }
    }

    private static function normalizeDayName($day) {
        $map = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ];

        $key = strtolower(trim((string) $day));
        return $map[$key] ?? null;
    }

    private static function isValidTimeRange($start, $end) {
        if ($start === '' || $end === '') {
            return false;
        }

        $startTimestamp = strtotime('1970-01-01 ' . $start);
        $endTimestamp = strtotime('1970-01-01 ' . $end);

        return $startTimestamp !== false && $endTimestamp !== false && $startTimestamp < $endTimestamp;
    }

    private static function formatTimeLabel($time) {
        $time = trim((string) $time);
        if ($time === '') {
            return '';
        }

        $timestamp = strtotime('1970-01-01 ' . $time);
        if ($timestamp === false) {
            return $time;
        }

        return date('g:i A', $timestamp);
    }

    private static function labelDeliveryMode($value) {
        $map = [
            'online' => 'Online',
            'in_person' => 'In-person',
            'both' => 'Online or in-person',
        ];

        $key = strtolower(trim((string) $value));
        return $map[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    private static function slugify($value) {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim((string) $value, '-') ?: substr(sha1((string) $value), 0, 12);
    }
}
