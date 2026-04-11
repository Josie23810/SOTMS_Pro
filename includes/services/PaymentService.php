<?php

require_once __DIR__ . '/PesapalService.php';

class PaymentService {
    public static function supportedProviders() {
        return [
            'pesapal' => 'PesaPal',
        ];
    }

    public static function reviewActions() {
        return [
            'verify' => 'Verify Payment',
            'fail' => 'Mark Failed',
            'refund' => 'Refund',
        ];
    }

    public static function findStudentSessionForPayment(PDO $pdo, $sessionId, $studentId) {
        ensurePlatformStructures($pdo);

        $stmt = $pdo->prepare("
            SELECT s.*, u.name AS tutor_name
            FROM sessions s
            JOIN tutors t ON s.tutor_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE s.id = ? AND s.student_id = ?
            LIMIT 1
        ");
        $stmt->execute([$sessionId, $studentId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function submitSessionPayment(PDO $pdo, array $session, $studentId, $studentUserId, $provider = 'pesapal') {
        ensurePlatformStructures($pdo);

        if ($provider !== 'pesapal') {
            return [
                'success' => false,
                'type' => 'error',
                'message' => 'PesaPal is the only enabled payment channel.',
                'session' => $session,
            ];
        }

        if (($session['payment_status'] ?? '') === 'paid') {
            return [
                'success' => true,
                'type' => 'success',
                'message' => 'This session is already paid.',
                'session' => $session,
            ];
        }

        $existingPayment = self::findLatestPaymentForSession($pdo, $session['id']);
        if ($existingPayment) {
            if (($existingPayment['status'] ?? '') === 'paid') {
                $pdo->prepare("UPDATE sessions SET payment_status = 'paid' WHERE id = ?")->execute([$session['id']]);
                $session['payment_status'] = 'paid';

                return [
                    'success' => true,
                    'type' => 'success',
                    'message' => 'This session is already paid.',
                    'session' => $session,
                ];
            }

            if (in_array($existingPayment['status'], ['pending', 'gateway_submitted'], true)) {
                $storedData = self::decodePaymentData($existingPayment['payment_data'] ?? null);
                if (!empty($storedData['redirect_url'])) {
                    return [
                        'success' => true,
                        'type' => 'success',
                        'message' => 'Continue your PesaPal checkout.',
                        'session' => $session,
                        'redirect_url' => $storedData['redirect_url'],
                    ];
                }

                $pdo->prepare("UPDATE sessions SET payment_status = 'processing' WHERE id = ?")->execute([$session['id']]);
                $session['payment_status'] = 'processing';

                return [
                    'success' => true,
                    'type' => 'success',
                    'message' => 'A PesaPal payment is already awaiting confirmation.',
                    'session' => $session,
                ];
            }
        }

        try {
            $payer = self::fetchStudentPayerDetails($pdo, $studentUserId);
            $reference = self::buildReference($session['id']);
            $amount = (float) ($session['payment_amount'] ?: $session['amount'] ?: 500);

            $checkout = PesapalService::createCheckout([
                'reference' => $reference,
                'amount' => $amount,
                'currency' => 'KES',
                'description' => trim(($session['subject'] ?: 'Tutoring session') . ' with ' . ($session['tutor_name'] ?: 'Tutor')),
            ], $payer);

            $paymentData = [
                'workflow_state' => 'gateway_submitted',
                'student_user_id' => $studentUserId,
                'redirect_url' => $checkout['redirect_url'],
                'notification_id' => $checkout['notification_id'],
                'gateway_response' => $checkout['raw'],
                'submitted_at' => date('c'),
            ];

            $pdo->beginTransaction();

            $stmt = $pdo->prepare('
                INSERT INTO payments (
                    session_id, student_id, tutor_id, amount, currency, provider, tracking_id, reference, status, pesapal_txn_id, payment_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $session['id'],
                $studentId,
                $session['tutor_id'],
                $amount,
                'KES',
                'pesapal',
                $checkout['order_tracking_id'],
                $reference,
                'gateway_submitted',
                $checkout['order_tracking_id'],
                json_encode($paymentData),
            ]);

            $paymentId = (int) $pdo->lastInsertId();
            self::logPaymentEvent($pdo, $paymentId, 'gateway_submitted', 'PesaPal checkout created.', $studentUserId, [
                'provider' => 'pesapal',
                'reference' => $reference,
                'tracking_id' => $checkout['order_tracking_id'],
                'redirect_url' => $checkout['redirect_url'],
            ]);

            $pdo->prepare("UPDATE sessions SET payment_status = 'processing' WHERE id = ?")->execute([$session['id']]);

            $pdo->commit();
            $session['payment_status'] = 'processing';

            return [
                'success' => true,
                'type' => 'success',
                'message' => 'Redirecting to PesaPal checkout.',
                'session' => $session,
                'redirect_url' => $checkout['redirect_url'],
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('PesaPal checkout error: ' . $e->getMessage());

            return [
                'success' => false,
                'type' => 'error',
                'message' => $e->getMessage(),
                'session' => $session,
            ];
        }
    }

    public static function findPaymentByReference(PDO $pdo, $reference) {
        ensurePlatformStructures($pdo);

        $stmt = $pdo->prepare("SELECT * FROM payments WHERE reference = ? LIMIT 1");
        $stmt->execute([$reference]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findPaymentByTrackingId(PDO $pdo, $trackingId) {
        ensurePlatformStructures($pdo);

        $stmt = $pdo->prepare("SELECT * FROM payments WHERE tracking_id = ? OR pesapal_txn_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$trackingId, $trackingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function fetchPaymentStatusContext(PDO $pdo, $reference) {
        ensurePlatformStructures($pdo);

        $stmt = $pdo->prepare("
            SELECT
                p.*,
                s.subject,
                s.payment_status,
                s.session_date,
                tutor_user.name AS tutor_name
            FROM payments p
            LEFT JOIN sessions s ON s.id = p.session_id
            LEFT JOIN tutors t ON s.tutor_id = t.id
            LEFT JOIN users tutor_user ON tutor_user.id = t.user_id
            WHERE p.reference = ?
            ORDER BY p.id DESC
            LIMIT 1
        ");
        $stmt->execute([$reference]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function transitionPaymentStatus(PDO $pdo, $paymentId, $targetStatus, $actorUserId = null, $note = '', array $attributes = []) {
        ensurePlatformStructures($pdo);

        $allowedStatuses = ['pending', 'gateway_submitted', 'paid', 'failed', 'refunded'];
        if (!in_array($targetStatus, $allowedStatuses, true)) {
            throw new InvalidArgumentException('Unsupported payment status.');
        }

        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$payment) {
            throw new RuntimeException('Payment not found.');
        }

        $paymentData = self::decodePaymentData($payment['payment_data'] ?? null);
        if (!empty($attributes)) {
            $paymentData = array_merge($paymentData, $attributes);
        }

        $updateFields = ['status = ?', 'updated_at = CURRENT_TIMESTAMP', 'payment_data = ?'];
        $params = [$targetStatus, !empty($paymentData) ? json_encode($paymentData) : null];

        if (!empty($attributes['tracking_id'])) {
            $updateFields[] = 'tracking_id = ?';
            $params[] = $attributes['tracking_id'];
        }

        if (!empty($attributes['pesapal_txn_id'])) {
            $updateFields[] = 'pesapal_txn_id = ?';
            $params[] = $attributes['pesapal_txn_id'];
        }

        if (!empty($attributes['provider'])) {
            $updateFields[] = 'provider = ?';
            $params[] = $attributes['provider'];
        }

        $params[] = $paymentId;

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE payments SET ' . implode(', ', $updateFields) . ' WHERE id = ?');
            $stmt->execute($params);

            $sessionStatus = self::mapSessionPaymentStatus($targetStatus);
            $stmt = $pdo->prepare('UPDATE sessions SET payment_status = ? WHERE id = ?');
            $stmt->execute([$sessionStatus, $payment['session_id']]);

            self::logPaymentEvent(
                $pdo,
                $paymentId,
                $targetStatus,
                $note !== '' ? $note : ('Payment moved to ' . $targetStatus . '.'),
                $actorUserId,
                $attributes
            );

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function reviewPayment(PDO $pdo, $paymentId, $action, $adminUserId, $note = '') {
        ensurePlatformStructures($pdo);

        $targetStatusMap = [
            'verify' => 'paid',
            'fail' => 'failed',
            'refund' => 'refunded',
        ];

        if (!isset($targetStatusMap[$action])) {
            throw new InvalidArgumentException('Unsupported payment review action.');
        }

        self::transitionPaymentStatus($pdo, $paymentId, $targetStatusMap[$action], $adminUserId, trim((string) $note), [
            'review_action' => $action,
        ]);
    }

    public static function fetchPaymentReviewQueue(PDO $pdo) {
        ensurePlatformStructures($pdo);

        $stmt = $pdo->query("
            SELECT
                p.id,
                p.reference,
                p.provider,
                p.amount,
                p.status,
                p.created_at,
                p.updated_at,
                s.id AS session_id,
                s.subject,
                s.payment_status,
                s.session_date,
                student_user.name AS student_name,
                tutor_user.name AS tutor_name
            FROM payments p
            JOIN sessions s ON s.id = p.session_id
            LEFT JOIN students st ON s.student_id = st.id
            LEFT JOIN users student_user ON student_user.id = st.user_id
            LEFT JOIN tutors tt ON s.tutor_id = tt.id
            LEFT JOIN users tutor_user ON tutor_user.id = tt.user_id
            ORDER BY
                CASE
                    WHEN p.status = 'gateway_submitted' THEN 1
                    WHEN p.status = 'pending' THEN 2
                    WHEN p.status = 'failed' THEN 3
                    WHEN p.status = 'refunded' THEN 4
                    ELSE 5
                END,
                p.updated_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function findLatestPaymentForSession(PDO $pdo, $sessionId) {
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE session_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private static function fetchStudentPayerDetails(PDO $pdo, $studentUserId) {
        $stmt = $pdo->prepare("
            SELECT
                u.name,
                u.email,
                sp.phone,
                sp.location
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$studentUserId]);
        $payer = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'name' => trim((string) ($payer['name'] ?? 'Student')),
            'email' => trim((string) ($payer['email'] ?? '')),
            'phone' => trim((string) ($payer['phone'] ?? '')),
            'city' => trim((string) ($payer['location'] ?? '')),
            'address_line' => 'SOTMS Pro',
        ];
    }

    private static function buildReference($sessionId) {
        return 'SOTMS-PES-' . $sessionId . '-' . date('YmdHis');
    }

    private static function decodePaymentData($value) {
        if (empty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function logPaymentEvent(PDO $pdo, $paymentId, $eventType, $eventNote = '', $createdBy = null, array $eventData = []) {
        $stmt = $pdo->prepare('
            INSERT INTO payment_events (payment_id, event_type, event_note, event_data, created_by)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $paymentId,
            $eventType,
            $eventNote,
            !empty($eventData) ? json_encode($eventData) : null,
            $createdBy,
        ]);
    }

    private static function mapSessionPaymentStatus($paymentStatus) {
        $map = [
            'pending' => 'unpaid',
            'gateway_submitted' => 'processing',
            'paid' => 'paid',
            'failed' => 'failed',
            'refunded' => 'refunded',
        ];

        return $map[$paymentStatus] ?? 'unpaid';
    }
}
