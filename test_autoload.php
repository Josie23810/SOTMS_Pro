<?php
echo "Testing from root\n";
require 'vendor/autoload.php';
echo "Autoloader loaded\n";
if (class_exists('Pesapal\\Pesapal')) {
    echo "Pesapal class found in root!\n";
} else {
    echo "Pesapal class NOT found in root\n";
}

echo "\n--- Simulating student context ---\n";
chdir('student');
echo "Current dir: " . getcwd() . "\n";
require '../vendor/autoload.php';
if (class_exists('Pesapal\\Pesapal')) {
    echo "Pesapal class found from student dir!\n";
} else {
    echo "Pesapal class NOT found from student dir\n";
}
echo "Autoloader file exists: " . (file_exists('../vendor/autoload.php') ? 'YES' : 'NO') . "\n";
echo "Pesapal.php exists: " . (file_exists('../vendor/pesapal-php/Pesapal.php') ? 'YES' : 'NO') . "\n";
?>

