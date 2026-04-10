<?php
// Prevent any accidental whitespace/HTML from being sent before the JSON
ob_clean(); 
header("Content-Type: application/json");
require_once '../config/db.php';

// 1. Get the reference (either CheckoutRequestID or SOTMS ref)
$reference = $_GET['ref'] ?? '';

if (empty($reference)) {
    echo json_encode(['status' => 'ERROR', 'message' => 'No reference provided']);
    exit;
}

try {
    /**
     * 2. Database Query
     * We check both the 'checkout_request_id' (from M-Pesa) and 
     * a general 'reference' column just in case.
     */
    $stmt = $pdo->prepare("
        SELECT status 
        FROM payments 
        WHERE checkout_request_id = ? OR reference = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $stmt->execute([$reference, $reference]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($payment) {
        $status = strtoupper($payment['status']);
        
        // 3. Logic for Success/Failure/Pending
        if (in_array($status, ['SUCCESS', 'COMPLETED', 'SUCCESSFUL', '0'])) {
            echo json_encode(['status' => 'SUCCESS']);
        } elseif (in_array($status, ['CANCELLED', 'FAILED', '1'])) {
            echo json_encode(['status' => 'FAILED']);
        } else {
            // It exists in the DB but is still 'PENDING'
            echo json_encode(['status' => 'PENDING']);
        }
    } else {
        // Payment hasn't hit our database yet
        echo json_encode(['status' => 'PENDING']);
    }

} catch (PDOException $e) {
    // Log the error for debugging, but don't break the JSON
    error_log("Payment Check Error: " . $e->getMessage());
    echo json_encode(['status' => 'ERROR', 'message' => 'Database connection failed']);
}
exit;