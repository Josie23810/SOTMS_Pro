<?php

class TutorVerificationService {
    public static function allowedDecisions() {
        return [
            'submitted' => 'Submitted',
            'under_review' => 'Under Review',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
        ];
    }

    public static function determineStatusForProfileUpdate($existingProfile, array $newData, $hasNewQualificationDocument) {
        if (!$existingProfile) {
            return 'submitted';
        }

        $currentStatus = trim((string) ($existingProfile['verification_status'] ?? 'submitted')) ?: 'submitted';
        if ($currentStatus === 'verified' && !self::verificationSensitiveFieldsChanged($existingProfile, $newData, $hasNewQualificationDocument)) {
            return 'verified';
        }

        if ($currentStatus === 'under_review' && !self::verificationSensitiveFieldsChanged($existingProfile, $newData, $hasNewQualificationDocument)) {
            return 'under_review';
        }

        return 'submitted';
    }

    public static function reviewTutor(PDO $pdo, $tutorUserId, $adminUserId, $decision, $notes = '') {
        ensurePlatformStructures($pdo);

        $allowed = self::allowedDecisions();
        if (!isset($allowed[$decision])) {
            throw new InvalidArgumentException('Unsupported verification decision.');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE tutor_profiles SET verification_status = ? WHERE user_id = ?');
            $stmt->execute([$decision, $tutorUserId]);

            $stmt = $pdo->prepare('
                INSERT INTO tutor_verification_reviews (tutor_user_id, admin_user_id, decision, review_notes)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([$tutorUserId, $adminUserId, $decision, trim((string) $notes)]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function fetchVerificationQueue(PDO $pdo) {
        ensurePlatformStructures($pdo);

        $stmt = $pdo->query("
            SELECT
                u.id AS tutor_user_id,
                u.name AS account_name,
                u.email AS account_email,
                tp.full_name,
                tp.email,
                tp.id_number,
                tp.qualifications,
                tp.qualification_document,
                tp.location,
                tp.verification_status,
                tp.updated_at,
                r.decision AS last_decision,
                r.review_notes AS last_review_notes,
                r.created_at AS last_reviewed_at,
                admin_user.name AS reviewed_by_name
            FROM users u
            JOIN tutor_profiles tp ON tp.user_id = u.id
            LEFT JOIN (
                SELECT x.*
                FROM tutor_verification_reviews x
                INNER JOIN (
                    SELECT tutor_user_id, MAX(id) AS max_id
                    FROM tutor_verification_reviews
                    GROUP BY tutor_user_id
                ) latest ON latest.tutor_user_id = x.tutor_user_id AND latest.max_id = x.id
            ) r ON r.tutor_user_id = u.id
            LEFT JOIN users admin_user ON admin_user.id = r.admin_user_id
            WHERE u.role = 'tutor'
            ORDER BY
                CASE
                    WHEN tp.verification_status = 'submitted' THEN 1
                    WHEN tp.verification_status = 'under_review' THEN 2
                    WHEN tp.verification_status = 'rejected' THEN 3
                    ELSE 4
                END,
                tp.updated_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function verificationSensitiveFieldsChanged($existingProfile, array $newData, $hasNewQualificationDocument) {
        if ($hasNewQualificationDocument) {
            return true;
        }

        $fieldPairs = [
            'full_name' => $newData['full_name'] ?? '',
            'email' => $newData['email'] ?? '',
            'id_number' => $newData['id_number'] ?? '',
            'qualifications' => $newData['qualifications'] ?? '',
        ];

        foreach ($fieldPairs as $field => $newValue) {
            if (trim((string) ($existingProfile[$field] ?? '')) !== trim((string) $newValue)) {
                return true;
            }
        }

        return false;
    }
}
