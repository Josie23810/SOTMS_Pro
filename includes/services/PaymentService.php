<?php

class PaymentService {
    public static function supportedProviders() {
        return [
            'mpesa' => 'M-Pesa',
            'pesapal' => 'Pesapal',
            'paypal' => 'PayPal',
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

    public static function submitSessionPayment(PDO $pdo, array $session, $studentId, $studentUserId, $provider) {
        ensurePlatformStructures($pdo);

        $providers = self::supportedProviders();
        if (!isset($providers[$provider])) {
            return [
                'success' => false,
                'type' => 'error',
                'message' => 'Please select a valid payment channel.',
                'session' => $session,
            ];
        }

        if (($session['payment_status'] ?? '') === 'paid') {
            return [
                'success' => true,
                'type' => 'success',
                'message' => 'This session already has a paid payment record.',
                'session' => $session,
            ];
        }

        $stmt = $pdo->prepare("SELECT * FROM payments WHERE session_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$session['id']]);
        $existingPayment = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existingPayment) {
            if (($existingPayment['status'] ?? '') === 'paid') {
                $updateStmt = $pdo->prepare("UPDATE sessions SET payment_status = 'paid' WHERE id = ?");
                $updateStmt->execute([$session['id']]);
                $session['payment_status'] = 'paid';

                return [
                    'success' => true,
                    'type' => 'success',
                    'message' => 'This session already has a paid payment record.',
                    'session' => $session,
                ];
            }

            if (in_array($existingPayment['status'], ['pending', 'gateway_submitted'], true)) {
                $updateStmt = $pdo->prepare("UPDATE sessions SET payment_status = 'processing' WHERE id = ?");
                $updateStmt->execute([$session['id']]);
                $session['payment_status'] = 'processing';

                return [
                    'success' => true,
                    'type' => 'success',
                    'message' => 'A payment submission is already awaiting verification.',
                    'session' => $session,
                ];
            }
        }

        $reference = 'SOTMS-' . strtoupper(substr($provider, 0, 3)) . '-' . time() . '-' . $session['id'];
        $amount = (float) ($session['payment_amount'] ?: $session['amount'] ?: 500);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('
                INSERT INTO payments (
                    session_id, student_id, tutor_id, amount, currency, provider, tracking_id, reference, status, payment_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $session['id'],
                $studentId,
                $session['tutor_id'],
                $amount,
                'KES',
                $provider,
                $reference,
                $reference,
                'gateway_submitted',
                json_encode([
                    'mode' => 'demo',
                    'workflow_state' => 'gateway_submitted',
                    'student_user_id' => $studentUserId,
                    'submitted_at' => date('c'),
                ]),
            ]);

            $paymentId = (int) $pdo->lastInsertId();
            self::logPaymentEvent($pdo, $paymentId, 'submitted', 'Payment submitted by student checkout.', $studentUserId, [
                'provider' => $provider,
                'reference' => $reference,
            ]);

            $stmt = $pdo->prepare("UPDATE sessions SET payment_status = 'processing' WHERE id = ?");
            $stmt->execute([$session['id']]);

            $pdo->commit();
            $session['payment_status'] = 'processing';

            return [
                'success' => true,
                'type' => 'success',
                'message' => 'Payment submitted successfully and is now awaiting verification.',
                'session' => $session,
            ];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Pay session error: ' . $e->getMessage());

            return [
                'success' => false,
                'type' => 'error',
                'message' => 'Payment could not be recorded.',
                'session' => $session,
            ];
        }
    }

    public static function recordDemoSessionPayment(PDO $pdo, array $session, $studentId, $studentUserId, $provider) {
        return self::submitSessionPayment($pdo, $session, $studentId, $studentUserId, $provider);
    }

    public static function findPaymentByReference(PDO $pdo, $reference) {
        ensurePlatformStructures($pdo);

        $stmt = $pdo->prepare("SELECT * FROM payments WHERE reference = ? LIMIT 1");
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

        $updateFields = ['status = ?', 'updated_at = CURRENT_TIMESTAMP'];
        $params = [$targetStatus];

        if (!empty($attributes['pesapal_txn_id'])) {
            $updateFields[] = 'pesapal_txn_id = ?';
            $params[] = $attributes['pesapal_txn_id'];
        }
        if (!empty($attributes['paypal_payment_id'])) {
            $updateFields[] = 'paypal_payment_id = ?';
            $params[] = $attributes['paypal_payment_id'];
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
