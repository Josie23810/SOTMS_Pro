<?php
// SOTMS_PRO/config/google_config.php

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
$googleAuthAvailable = false;
$googleAuthError = '';
$googleLoginUrl = 'auth/google_callback.php?mock=1';
$client = null;

if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;

    // Your Google App Credentials
    $clientID = getenv('GOOGLE_CLIENT_ID') ?: '631670923763-ru8o070pf2u4dn136rv9inca1hf95pv3.apps.googleusercontent.com';
    $clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: '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';
    $redirectUri = 'http://localhost/SOTMS_PRO/auth/google_callback.php';

    if (empty($clientID) || empty($clientSecret)) {
        $googleAuthError = 'Google OAuth is not configured correctly. Please set a valid client secret.';
    } else {
        // Create and Configure the Google Client
        $client = new Google_Client();
        $client->setClientId($clientID);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->addScope(['email', 'profile']);
        $googleAuthAvailable = true;
    }
}
?>