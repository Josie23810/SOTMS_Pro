<?php
require_once 'config/db.php';

echo "=== Simple Database Debug ===\n";

try {
    // Test database connection
    echo "Database connection: OK\n";
    
    // Check if study_levels table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'study_levels'");
    $tableExists = $stmt->rowCount() > 0;
    echo "Study levels table exists: " . ($tableExists ? "YES" : "NO") . "\n";
    
    if ($tableExists) {
        // Count records
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM study_levels");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "Study levels count: $count\n";
        
        // Show first 5 records
        if ($count > 0) {
            $stmt = $pdo->query("SELECT name, education_level FROM study_levels LIMIT 5");
            $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "First 5 levels:\n";
            foreach ($levels as $level) {
                echo "- " . $level['name'] . " (" . $level['education_level'] . ")\n";
            }
        }
    }
    
    // Test ProfileTaxonomyService
    echo "\n=== ProfileTaxonomyService Test ===\n";
    require_once 'includes/user_helpers.php';
    require_once 'includes/services/ProfileTaxonomyService.php';
    
    $catalogOptions = ProfileTaxonomyService::getCatalogOptions($pdo);
    echo "Study levels from service: " . count($catalogOptions['study_levels']) . "\n";
    
    if (count($catalogOptions['study_levels']) > 0) {
        echo "First 5 from service:\n";
        foreach (array_slice($catalogOptions['study_levels'], 0, 5) as $level) {
            echo "- $level\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Debug Complete ===\n";
?>
