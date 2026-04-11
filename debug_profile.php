<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';
require_once 'includes/user_helpers.php';
require_once 'includes/services/ProfileTaxonomyService.php';
checkAccess(['student']);

ensurePlatformStructures($pdo);
$catalogOptions = ProfileTaxonomyService::getCatalogOptions($pdo);

// Debug output
echo "<h2>Debug Student Profile Data</h2>";

echo "<h3>Catalog Options:</h3>";
echo "<pre>" . print_r($catalogOptions, true) . "</pre>";

echo "<h3>Study Levels Count:</h3>";
echo "<p>Database study levels: " . count($catalogOptions['study_levels']) . "</p>";

echo "<h3>Hardcoded Suggestions:</h3>";
$studyLevelSuggestions = [
    'Grade 4', 'Grade 5', 'Grade 6',
    'Grade 7', 'Grade 8', 'Grade 9',
    'Form 1', 'Form 2', 'Form 3', 'Form 4',
    'Certificate Year 1', 'Certificate Year 2',
    'Diploma Year 1', 'Diploma Year 2', 'Diploma Final Year',
    "Bachelor's Year 1", "Bachelor's Year 2", "Bachelor's Year 3", "Bachelor's Year 4",
    'Postgraduate Diploma',
    "Master's Coursework", "Master's Research",
    'PhD Year 1', 'PhD Candidate',
    'CPA', 'ACCA', 'Professional Certification'
];
echo "<p>Hardcoded suggestions: " . count($studyLevelSuggestions) . "</p>";

echo "<h3>Merged Options:</h3>";
$mergedOptions = array_unique(array_merge($studyLevelSuggestions, $catalogOptions['study_levels']));
echo "<p>Total merged options: " . count($mergedOptions) . "</p>";
echo "<pre>" . print_r($mergedOptions, true) . "</pre>";

echo "<h3>HTML Datalist Output:</h3>";
echo "<datalist id='study_level_options'>";
foreach ($mergedOptions as $studyLevelOption) {
    echo "<option value='" . htmlspecialchars($studyLevelOption) . "'></option>";
}
echo "</datalist>";

echo "<hr>";
echo "<p><a href='student/profile.php'>Go to actual Student Profile</a></p>";
?>
