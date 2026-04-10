<?php

class TutorMatchService {
    public static function getMatches(PDO $pdo, $studentUserId, $limit = null) {
        ensurePlatformStructures($pdo);

        $studentProfile = fetchStudentProfile($pdo, $studentUserId);
        $studentSubjects = $studentProfile['subject_names'] ?? normalizeCsvArray($studentProfile['subjects_interested'] ?? '');
        $studentCurricula = $studentProfile['curriculum_names'] ?? normalizeCsvArray($studentProfile['curriculum'] ?? '');
        $studentLevels = $studentProfile['study_level_names'] ?? normalizeCsvArray($studentProfile['level_of_study'] ?? ($studentProfile['education_level'] ?? ''));
        $studentLocation = trim((string) ($studentProfile['location'] ?? ''));

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

        foreach ($rows as &$row) {
            $score = 0;
            $reasons = [];

            if (self::hasStructuredOverlap($studentCurricula, $row['curriculum_names'] ?? [], $row['curriculum_specialties'] ?? '')) {
                $score += 4;
                $reasons[] = 'Curriculum match';
            }

            if (self::hasStructuredOverlap($studentLevels, $row['study_level_names'] ?? [], $row['study_levels_supported'] ?? '')) {
                $score += 3;
                $reasons[] = 'Level match';
            }

            $sharedSubjects = self::findSharedSubjects($studentSubjects, $row['subject_names'] ?? normalizeCsvArray($row['subjects_taught'] ?? ''));
            if (!empty($sharedSubjects)) {
                $score += 2;
                $reasons[] = 'Subject overlap';
            }

            $serviceAreaText = !empty($row['service_area_names']) ? implode(', ', $row['service_area_names']) : ($row['service_areas'] ?? '');
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

            $row['match_score'] = $score;
            $row['match_reasons'] = $reasons;
            $row['shared_subjects'] = $sharedSubjects;
            $row['display_email'] = $row['email'] ?: $row['account_email'];
            $row['session_rate'] = parseRateAmount($row['hourly_rate'] ?? null);
        }
        unset($row);

        usort($rows, function ($left, $right) {
            if ($left['match_score'] === $right['match_score']) {
                return strcasecmp($left['name'], $right['name']);
            }

            return $right['match_score'] <=> $left['match_score'];
        });

        if ($limit !== null) {
            $rows = array_slice($rows, 0, (int) $limit);
        }

        return [$studentProfile, $rows];
    }

    private static function hasStructuredOverlap(array $preferredValues, array $structuredValues, $fallbackCsv) {
        foreach ($preferredValues as $preferredValue) {
            if (self::valueInList($preferredValue, $structuredValues) || csvContains($fallbackCsv, $preferredValue)) {
                return true;
            }
        }

        return false;
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
