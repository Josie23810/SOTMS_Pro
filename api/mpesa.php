<?php
header("Content-Type: application/json");
require_once '../config/db.php'; 

$json = file_get_contents('php://input');
$data = json_decode($json, true);

// 1. YOUR CREDENTIALS
$consumerKey = 'Y2fdb88uY8tS6vGiv2uDab3FzkQIKTskLwLHxZj7QUBDTSLw'; 
$consumerSecret = '4sbj6EhnkcSMewpHVpNvqvH6g7qPeA5Wd8tykTZGpuLuhEP8cycNayR50LJdzUcB';
$shortCode = '174379'; 
$passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';

// 2. UPDATE THIS WITH YOUR ACTIVE NGROK LINK
$baseUrl = 'https://xxxx-xxxx.ngrok-free.app'; // <--- Change this!
$callbackUrl = $baseUrl . '/SOTMS_Pro/callback_handler.php';

$phone = $data['phone'];
$amount = 1; 
$sessionId = $data['session_id'] ?? 0;

// 3. GET ACCESS TOKEN
$authUrl = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
$ch = curl_init($authUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret)]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
$auth = json_decode(curl_exec($ch));
$accessToken = $auth->access_token;
curl_close($ch);

// 4. INITIATE STK PUSH
$stkUrl = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
$timestamp = date('YmdHis');
$password = base64_encode($shortCode . $passkey . $timestamp);

$curl_post_data = [
    'BusinessShortCode' => $shortCode,
    'Password'          => $password,
    'Timestamp'         => $timestamp,
    'TransactionType'   => 'CustomerPayBillOnline',
    'Amount'            => $amount,
    'PartyA'            => $phone,
    'PartyB'            => $shortCode,
    'PhoneNumber'       => $phone,
    'CallBackURL'       => $callbackUrl,
    'AccountReference'  => 'SOTMS' . $sessionId,
    'TransactionDesc'   => 'Payment for Session'
];

$ch = curl_init($stkUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$result = json_decode($response);
curl_close($ch);

// 5. LOG AND RESPOND
if (isset($result->ResponseCode) && $result->ResponseCode == "0") {
    // Save the CheckoutRequestID to your database so check_payment.php can find it
    $stmt = $pdo->prepare("INSERT INTO payments (session_id, checkout_request_id, amount, status) VALUES (?, ?, ?, 'PENDING')");
    $stmt->execute([$sessionId, $result->CheckoutRequestID, $amount]);
    echo $response;
} else {
    echo json_encode(['success' => false, 'error' => $result->errorMessage ?? 'Request Failed']);
}