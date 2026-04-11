<?php
require_once 'config/db.php';

echo "=== Complete Migration Setup ===\n";

try {
    // Read and execute the complete migration SQL
    $sqlFile = __DIR__ . '/complete_migration_setup.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Could not read migration file");
    }
    
    echo "Executing complete migration...\n";
    $pdo->exec($sql);
    echo "Complete migration executed successfully!\n";
    
    // Verify study levels
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM study_levels");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Study levels in database: $count\n";
    
    // Test ProfileTaxonomyService
    require_once 'includes/user_helpers.php';
    require_once 'includes/services/ProfileTaxonomyService.php';
    
    echo "Testing ProfileTaxonomyService...\n";
    $catalogOptions = ProfileTaxonomyService::getCatalogOptions($pdo);
    echo "Study levels from service: " . count($catalogOptions['study_levels']) . "\n";
    
    if (count($catalogOptions['study_levels']) > 0) {
        echo "Sample study levels:\n";
        foreach (array_slice($catalogOptions['study_levels'], 0, 5) as $level) {
            echo "- $level\n";
        }
    }
    
    echo "\n=== Migration Complete ===\n";
    echo "You can now test the student profile autocomplete!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Details: " . $e->getTraceAsString() . "\n";
}
?>
