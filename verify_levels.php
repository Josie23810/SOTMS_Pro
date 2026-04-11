<?php
require_once 'config/db.php';

echo "<h2>Verify Study Levels</h2>";

try {
    // Check total count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM study_levels");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Total study levels: $total</p>";
    
    // Show by education level
    $stmt = $pdo->query("SELECT education_level, COUNT(*) as count FROM study_levels GROUP BY education_level ORDER BY count DESC");
    $byLevel = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>By Education Level:</h3>";
    foreach ($byLevel as $level) {
        echo "<p><strong>" . htmlspecialchars($level['education_level']) . ":</strong> " . $level['count'] . " levels</p>";
    }
    
    // Test ProfileTaxonomyService
    echo "<h3>ProfileTaxonomyService Test:</h3>";
    require_once 'includes/services/ProfileTaxonomyService.php';
    $catalogOptions = ProfileTaxonomyService::getCatalogOptions($pdo);
    
    echo "<p>Study levels returned: " . count($catalogOptions['study_levels']) . "</p>";
    echo "<p>First 10 levels:</p>";
    echo "<ul>";
    foreach (array_slice($catalogOptions['study_levels'], 0, 10) as $level) {
        echo "<li>" . htmlspecialchars($level) . "</li>";
    }
    echo "</ul>";
    
    // Show high school levels specifically
    echo "<h3>High School Levels:</h3>";
    $stmt = $pdo->query("SELECT name FROM study_levels WHERE education_level = 'high_school' ORDER BY name");
    $highSchool = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<ul>";
    foreach ($highSchool as $level) {
        echo "<li>" . htmlspecialchars($level) . "</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='student/profile.php'>Go to Student Profile</a></p>";
?>
