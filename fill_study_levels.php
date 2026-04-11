<?php
require_once 'config/db.php';

echo "<h2>Filling Study Levels Data</h2>";

// Study levels data
$studyLevels = [
    ['Grade 4', 'primary', 'grade-4'],
    ['Grade 5', 'primary', 'grade-5'],
    ['Grade 6', 'primary', 'grade-6'],
    ['Grade 7', 'junior_secondary', 'grade-7'],
    ['Grade 8', 'junior_secondary', 'grade-8'],
    ['Grade 9', 'junior_secondary', 'grade-9'],
    ['Form 1', 'high_school', 'form-1'],
    ['Form 2', 'high_school', 'form-2'],
    ['Form 3', 'high_school', 'form-3'],
    ['Form 4', 'high_school', 'form-4'],
    ['Certificate Year 1', 'certificate', 'certificate-year-1'],
    ['Certificate Year 2', 'certificate', 'certificate-year-2'],
    ['Diploma Year 1', 'diploma', 'diploma-year-1'],
    ['Diploma Year 2', 'diploma', 'diploma-year-2'],
    ['Diploma Final Year', 'diploma', 'diploma-final-year'],
    ['Bachelor\'s Year 1', 'bachelors', 'bachelors-year-1'],
    ['Bachelor\'s Year 2', 'bachelors', 'bachelors-year-2'],
    ['Bachelor\'s Year 3', 'bachelors', 'bachelors-year-3'],
    ['Bachelor\'s Year 4', 'bachelors', 'bachelors-year-4'],
    ['Postgraduate Diploma', 'postgraduate_diploma', 'postgraduate-diploma'],
    ['Master\'s Coursework', 'masters', 'masters-coursework'],
    ['Master\'s Research', 'masters', 'masters-research'],
    ['PhD Year 1', 'phd', 'phd-year-1'],
    ['PhD Candidate', 'phd', 'phd-candidate'],
    ['CPA', 'professional', 'cpa'],
    ['ACCA', 'professional', 'acca'],
    ['Professional Certification', 'professional', 'professional-certification']
];

try {
    $pdo->beginTransaction();
    
    $inserted = 0;
    foreach ($studyLevels as $level) {
        $stmt = $pdo->prepare("
            INSERT INTO study_levels (name, education_level, slug) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                name = VALUES(name), 
                education_level = VALUES(education_level)
        ");
        $stmt->execute($level);
        if ($stmt->rowCount() > 0) {
            $inserted++;
        }
    }
    
    $pdo->commit();
    echo "<p>Successfully inserted $inserted new study levels</p>";
    
    // Verify data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM study_levels");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>Total study levels in database: $count</p>";
    
    // Show sample data
    $stmt = $pdo->query("SELECT name, education_level FROM study_levels WHERE education_level = 'high_school' ORDER BY name");
    $highSchoolLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>High School Levels:</h3>";
    echo "<ul>";
    foreach ($highSchoolLevels as $level) {
        echo "<li>" . htmlspecialchars($level['name']) . "</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='student/profile.php'>Test Student Profile</a></p>";
?>
