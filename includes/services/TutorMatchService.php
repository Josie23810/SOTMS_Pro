<?php

class TutorMatchService {
    public static function getMatches(PDO $pdo, $studentUserId, $limit = null) {
        ensurePlatformStructures($pdo);

        $studentProfile = fetchStudentProfile($pdo, $studentUserId) ?: [];
        $studentSubjects = self::resolveValues($studentProfile['subject_names'] ?? [], $studentProfile['subjects_interested'] ?? '');
        $studentCurricula = self::resolveValues($studentProfile['curriculum_names'] ?? [], $studentProfile['curriculum'] ?? '');
        $studentLevels = self::resolveValues($studentProfile['study_level_names'] ?? [], $studentProfile['level_of_study'] ?? '');
        $studentLocation = trim((string) ($studentProfile['location'] ?? ''));
        $studentEducationBucket = self::inferEducationBucket($studentProfile['education_level'] ?? '', array_merge($studentLevels, $studentCurricula));
        $hasStudentPreferences = !empty($studentSubjects) || !empty($studentCurricula) || !empty($studentLevels) || $studentLocation !== '';

        $stmt = $pdo->prepare('
            SELECT
                u.id AS user_id,
                u.name,
                u.email AS account_email,
                t.id AS tutor_id,
                tp.*
            FROM users u
            JOIN tutors t ON t.user_id = u.id
            LEFT JOIN tutor_profiles tp ON tp.user_id = u.id
            WHERE u.role = "tutor"
            ORDER BY u.name
        ');
        $stmt->execute();
        $rows = ProfileTaxonomyService::attachTutorSelectionsToRows($pdo, $stmt->fetchAll(PDO::FETCH_ASSOC));

        $matches = [];
        foreach ($rows as $row) {
            $score = 0;
            $reasons = [];

            $tutorSubjects = self::resolveValues($row['subject_names'] ?? [], $row['subjects_taught'] ?? '');
            $tutorCurricula = self::resolveValues($row['curriculum_names'] ?? [], $row['curriculum_specialties'] ?? '');
            $tutorLevels = self::resolveValues($row['study_level_names'] ?? [], $row['study_levels_supported'] ?? '');
            $tutorServiceAreas = self::resolveValues($row['service_area_names'] ?? [], $row['service_areas'] ?? '');

            if (self::hasCurriculumMatch($studentCurricula, $tutorCurricula, $row['curriculum_specialties'] ?? '')) {
                $score += 4;
                $reasons[] = 'Curriculum match';
            }

            if (self::hasStudyLevelMatch($studentLevels, $studentEducationBucket, $tutorLevels, $row['study_levels_supported'] ?? '')) {
                $score += 3;
                $reasons[] = 'Level match';
            }

            $sharedSubjects = self::findSharedSubjects($studentSubjects, $tutorSubjects);
            if (!empty($sharedSubjects)) {
                $score += 2;
                $reasons[] = 'Subject overlap';
            }

            $serviceAreaText = !empty($tutorServiceAreas) ? implode(', ', $tutorServiceAreas) : ($row['service_areas'] ?? '');
            list($locationScore, $matchedArea) = matchLocationScore($studentLocation, $row['location'] ?? '', $serviceAreaText);
            if ($locationScore > 0) {
                $score += $locationScore;
                $reasons[] = $matchedArea ? 'Near ' . $matchedArea : 'Nearby';
            }

            if (!empty($row['qualification_document'])) {
                $score += 1;
            }

            if (!empty($row['verification_status']) && strtolower((string) $row['verification_status']) === 'verified') {
                $score += 1;
                $reasons[] = 'Verified profile';
            }

            if ($hasStudentPreferences && $score === 0) {
                continue;
            }

            $row['match_score'] = $score;
            $row['match_reasons'] = array_values(array_unique($reasons));
            $row['shared_subjects'] = $sharedSubjects;
            $row['display_email'] = $row['email'] ?: $row['account_email'];
            $row['session_rate'] = parseRateAmount($row['hourly_rate'] ?? null);
            $matches[] = $row;
        }

        usort($matches, function ($left, $right) {
            if ($left['match_score'] === $right['match_score']) {
                return strcasecmp($left['name'], $right['name']);
            }

            return $right['match_score'] <=> $left['match_score'];
        });

        if ($limit !== null) {
            $matches = array_slice($matches, 0, (int) $limit);
        }

        return [$studentProfile, $matches];
    }

    private static function resolveValues(array $structuredValues, $fallbackValue) {
        $structuredValues = array_values(array_filter(array_map('trim', $structuredValues), function ($value) {
            return $value !== '';
        }));

        if (!empty($structuredValues)) {
            return $structuredValues;
        }

        return normalizeCsvArray($fallbackValue);
    }

    private static function hasCurriculumMatch(array $studentCurricula, array $tutorCurricula, $fallbackCsv) {
        if (self::hasStructuredOverlap($studentCurricula, $tutorCurricula, $fallbackCsv)) {
            return true;
        }

        $studentGroups = self::curriculumGroups($studentCurricula);
        $tutorGroups = self::curriculumGroups(array_merge($tutorCurricula, normalizeCsvArray($fallbackCsv)));

        return !empty(array_intersect($studentGroups, $tutorGroups));
    }

    private static function hasStudyLevelMatch(array $studentLevels, $studentEducationBucket, array $tutorLevels, $fallbackCsv) {
        if (self::hasStructuredOverlap($studentLevels, $tutorLevels, $fallbackCsv)) {
            return true;
        }

        $tutorBucket = self::inferEducationBucket('', array_merge($tutorLevels, normalizeCsvArray($fallbackCsv)));
        return $studentEducationBucket !== '' && $studentEducationBucket === $tutorBucket;
    }

    private static function hasStructuredOverlap(array $preferredValues, array $structuredValues, $fallbackCsv) {
        foreach ($preferredValues as $preferredValue) {
            if (self::valueInList($preferredValue, $structuredValues) || csvContains($fallbackCsv, $preferredValue)) {
                return true;
            }
        }

        return false;
    }

    private static function curriculumGroups(array $values) {
        $groups = [];

        foreach ($values as $value) {
            $normalized = strtolower((string) $value);
            if ($normalized === '') {
                continue;
            }

            if (strpos($normalized, 'cbc') !== false) {
                $groups['cbc'] = true;
            }
            if (preg_match('/8[\s-]?4[\s-]?4/', $normalized)) {
                $groups['844'] = true;
            }
            if (strpos($normalized, 'kcse') !== false) {
                $groups['kcse'] = true;
            }
            if (strpos($normalized, 'igcse') !== false) {
                $groups['igcse'] = true;
            }
            if (strpos($normalized, 'ib') !== false || strpos($normalized, 'international baccalaureate') !== false) {
                $groups['ib'] = true;
            }
            if (strpos($normalized, 'a-level') !== false || strpos($normalized, 'a level') !== false || strpos($normalized, 'alevel') !== false) {
                $groups['a_level'] = true;
            }
            if (strpos($normalized, 'tvet') !== false || strpos($normalized, 'technical') !== false || strpos($normalized, 'certificate') !== false || strpos($normalized, 'diploma') !== false) {
                $groups['tvet'] = true;
            }
            if (strpos($normalized, 'university') !== false || strpos($normalized, 'degree') !== false || strpos($normalized, 'bachelor') !== false || strpos($normalized, 'master') !== false || strpos($normalized, 'phd') !== false || strpos($normalized, 'doctorate') !== false || strpos($normalized, 'postgraduate') !== false) {
                $groups['university'] = true;
            }
            if (strpos($normalized, 'professional') !== false || strpos($normalized, 'cpa') !== false || strpos($normalized, 'acca') !== false || strpos($normalized, 'certification') !== false) {
                $groups['professional'] = true;
            }
        }

        return array_keys($groups);
    }

    private static function inferEducationBucket($educationLevel, array $values) {
        $candidates = array_merge([$educationLevel], $values);

        foreach ($candidates as $candidate) {
            $normalized = strtolower(trim((string) $candidate));
            if ($normalized === '') {
                continue;
            }

            if ($normalized === 'primary' || strpos($normalized, 'primary') !== false || preg_match('/grade\s*[1-6]\b/', $normalized)) {
                return 'primary';
            }
            if ($normalized === 'junior_secondary' || strpos($normalized, 'junior secondary') !== false || preg_match('/grade\s*[7-9]\b/', $normalized)) {
                return 'junior_secondary';
            }
            if ($normalized === 'high_school' || strpos($normalized, 'high school') !== false || strpos($normalized, 'secondary') !== false || preg_match('/form\s*[1-4]\b/', $normalized)) {
                return 'high_school';
            }
            if ($normalized === 'certificate' || strpos($normalized, 'certificate') !== false) {
                return 'certificate';
            }
            if ($normalized === 'diploma' || strpos($normalized, 'diploma') !== false) {
                return 'diploma';
            }
            if ($normalized === 'bachelors' || strpos($normalized, 'bachelor') !== false || strpos($normalized, 'undergraduate') !== false) {
                return 'bachelors';
            }
            if ($normalized === 'postgraduate_diploma' || strpos($normalized, 'postgraduate diploma') !== false) {
                return 'postgraduate_diploma';
            }
            if ($normalized === 'masters' || strpos($normalized, 'master') !== false) {
                return 'masters';
            }
            if ($normalized === 'phd' || strpos($normalized, 'phd') !== false || strpos($normalized, 'doctorate') !== false) {
                return 'phd';
            }
            if ($normalized === 'professional' || strpos($normalized, 'professional') !== false || strpos($normalized, 'cpa') !== false || strpos($normalized, 'acca') !== false) {
                return 'professional';
            }
            if (strpos($normalized, 'tvet') !== false) {
                return 'diploma';
            }
        }

        return '';
    }

    private static function findSharedSubjects(array $studentSubjects, array $tutorSubjects) {
        $sharedSubjects = [];

        foreach ($studentSubjects as $subject) {
            foreach ($tutorSubjects as $tutorSubject) {
                if (strcasecmp($subject, $tutorSubject) === 0) {
                    $sharedSubjects[] = $subject;
                }
            }
        }

        return array_values(array_unique($sharedSubjects));
    }

    private static function valueInList($needle, array $haystack) {
        foreach ($haystack as $item) {
            if (strcasecmp((string) $needle, (string) $item) === 0) {
                return true;
            }
        }

        return false;
    }
}
