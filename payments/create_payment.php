<?php
// payments/create_payment.php
require_once __DIR__ . '/../includes/db.php';

// Paystack public key
$publicKey = "pk_test_195b7969da18bd4facb0bbbb9917c19f61876ff2";

// Example: get order info from your system
$student_id = $_GET['student_id'] ?? 1;
$amount = 5000; // Amount in NGN or KES depending on your setup
$tracking_id = uniqid("TRX"); // unique tracking ID for payment

// Save a pending payment in your database
$stmt = $conn->prepare("INSERT INTO payments (tracking_id, student_id, amount, status, created_at) VALUES (?, ?, ?, 'PENDING', NOW())");
$stmt->bind_param("sii", $tracking_id, $student_id, $amount);
$stmt->execute();

// Redirect to Paystack checkout
?>
<!DOCTYPE html>
<html>
<head>
    <title>Pay with Paystack</title>
    <script src="https://js.paystack.co/v1/inline.js"></script>
</head>
<body>
    <button type="button" id="payBtn">Pay Now</button>

    <script>
    const payBtn = document.getElementById("payBtn");

    payBtn.addEventListener("click", function() {
        let handler = PaystackPop.setup({
            key: "<?= $publicKey ?>", // public key
            email: "student@example.com", // replace with student's email
            amount: <?= $amount ?>, // in the smallest currency unit (e.g., KES*100)
            ref: "<?= $tracking_id ?>",
            metadata: {
                custom_fields: [
                    {display_name: "Student ID", variable_name: "student_id", value: <?= $student_id ?> }
                ]
            },
            callback: function(response) {
                alert("Payment complete! Reference: " + response.reference);
                // Optionally redirect to a success page
                window.location.href = "payment_success.php?ref=" + response.reference;
            },
            onClose: function() {
                alert("Payment cancelled.");
            }
        });
        handler.openIframe();
    });
    </script>
</body>
</html>
require_once '../includes/auth_check.php';
checkAccess(['student']);

$sessionId = intval($_GET['session_id'] ?? $_GET['id'] ?? 0);
if (!$sessionId) {
    header('Location: ../student/schedule.php');
    exit();
}

header('Location: ../student/pay_session.php?id=' . $sessionId);
exit();
?>
