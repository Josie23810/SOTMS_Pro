<?php
return [
    'consumer_key' => getenv('PESAPAL_CONSUMER_KEY') ?: 'TslOQQNwLZjOc0XJUiCdGv8HklPBRmal',
    'consumer_secret' => getenv('PESAPAL_CONSUMER_SECRET') ?: 'jdvPBXCeXwx96Masow++Bq73H3U=',
    'environment' => getenv('PESAPAL_ENVIRONMENT') ?: 'live',
    'app_base_url' => getenv('PESAPAL_APP_BASE_URL') ?: 'https://kiesha-prerational-duke.ngrok-free.dev/SOTMS_Pro/SOTMS_Pro',
    'public_base_url' => getenv('PESAPAL_PUBLIC_BASE_URL') ?: 'https://kiesha-prerational-duke.ngrok-free.dev/SOTMS_Pro/SOTMS_Pro',
    'notification_id' => getenv('PESAPAL_NOTIFICATION_ID') ?: '',
    'callback_path' => '/payments/callback.php',
    'ipn_path' => '/payments/ipn.php',
    'cancel_path' => '/payments/fail.php?reason=cancelled',
    'ipn_notification_type' => 'GET',
    'branch' => 'SOTMS Pro',
];
?>

