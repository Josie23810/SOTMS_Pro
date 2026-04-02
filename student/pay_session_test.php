<?php
require '../../vendor/autoload.php';
use Pesapal\Pesapal;

echo "Autoloader loaded\n";
if (class_exists('Pesapal\\Pesapal')) {
    echo "Pesapal\\Pesapal class found!\n";
    $pesapal = new Pesapal('demo', 'demo');
    echo "Instance created successfully!\n";
} else {
    echo "Class NOT found\n";
    echo "Files: " . (file_exists('../vendor/pesapal-php/Pesapal.php') ? 'Pesapal.php EXISTS' : 'MISSING') . "\n";
}
?>

