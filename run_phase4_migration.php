<?php
require_once __DIR__ . '/config/db.php';

$migrationFile = __DIR__ . '/phase4_operations_workflow.sql';
if (!is_file($migrationFile)) {
    die("Migration file not found.\n");
}

$sql = file_get_contents($migrationFile);
if ($sql === false) {
    die("Unable to read migration file.\n");
}

try {
    $pdo->exec($sql);
    echo "Phase 4 operations workflow migration applied successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
