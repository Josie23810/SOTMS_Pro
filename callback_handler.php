<?php
require_once 'config/db.php';

$callbackJSONData = file_get_contents('php://input');
$callbackData = json_decode($callbackJSONData);

$resultCode = $callbackData->Body->stkCallback->ResultCode;
$checkoutRequestID = $callbackData->Body->stkCallback->CheckoutRequestID;

if ($resultCode == 0) {
    // Payment Successful! Update your database
    $stmt = $pdo->prepare("UPDATE payments SET status = 'SUCCESS' WHERE checkout_request_id = ?");
    $stmt->execute([$checkoutRequestID]);
} else {
    // Payment Failed or Cancelled
    $stmt = $pdo->prepare("UPDATE payments SET status = 'FAILED' WHERE checkout_request_id = ?");
    $stmt->execute([$checkoutRequestID]);
}

// Safaricom expects a success response
echo json_encode(["ResultCode" => 0, "ResultDesc" => "Accepted"]);