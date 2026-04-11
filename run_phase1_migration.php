<?php
require_once __DIR__ . '/config/db.php';

$migrationFile = __DIR__ . '/migrations/001_phase1_foundation.sql';
if (!is_file($migrationFile)) {
    die("Migration file not found.\n");
}

$sql = file_get_contents($migrationFile);
if ($sql === false) {
    die("Unable to read migration file.\n");
}

try {
    $pdo->exec($sql);
    echo "Phase 1 foundation migration applied successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
