<!DOCTYPE html>
<html>
<head>
    <title>Payment Success - SOTMS PRO</title>
    <style>
        body { font-family: Arial; max-width: 600px; margin: 50px auto; text-align: center; padding: 20px; }
        .success { color: green; font-size: 24px; }
    </style>
</head>
<body>
    <h1 class="success">✅ Payment Successful!</h1>
    <p>Your session payment has been processed. You will be redirected to your schedule.</p>
    <script>setTimeout(() => { window.location = '../student/schedule.php'; }, 3000);</script>
</body>
</html>

