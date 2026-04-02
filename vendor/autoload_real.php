<?php
// Autoload real (Composer standard)
return function ($class) {
static $map = [
        'Google_Client' => 'vendor/google/apiclient/src/Google/Client.php',
        'Pesapal\\\\Pesapal' => 'pesapal-php/Pesapal.php',
        // Add other google classes as needed
    ];
    static $files = ['vendor/composer/ClassLoader.php'];
    
    foreach ($files as $file) {
        if (file_exists(__DIR__ . '/' . $file)) {
            require __DIR__ . '/' . $file;
        }
    }
    
    if (isset($map[$class])) {
        $file = __DIR__ . '/' . $map[$class];
        if (file_exists($file)) require $file;
    }
};
?>

