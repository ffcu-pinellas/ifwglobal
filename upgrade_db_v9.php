<?php
// upgrade_db_v9.php - Add missing columns to IFW_kyc_fields
require_once __DIR__ . '/config.php';

$results = [];

// Add field_options column if not exists
try {
    $pdo->exec("ALTER TABLE IFW_kyc_fields ADD COLUMN field_options TEXT NULL AFTER field_type");
    $results[] = "✅ Added field_options column to IFW_kyc_fields";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column') || str_contains($e->getMessage(), 'already exists')) {
        $results[] = "ℹ️ field_options column already exists in IFW_kyc_fields";
    } else {
        $results[] = "❌ Error adding field_options: " . $e->getMessage();
    }
}

// Add sort_order column if not exists  
try {
    $pdo->exec("ALTER TABLE IFW_kyc_fields ADD COLUMN sort_order INT DEFAULT 0 AFTER is_required");
    $results[] = "✅ Added sort_order column to IFW_kyc_fields";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column') || str_contains($e->getMessage(), 'already exists')) {
        $results[] = "ℹ️ sort_order column already exists in IFW_kyc_fields";
    } else {
        $results[] = "❌ Error adding sort_order: " . $e->getMessage();
    }
}

// Add updated_at column to IFW_kyc_submissions if not exists
try {
    $pdo->exec("ALTER TABLE IFW_kyc_submissions ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER submitted_at");
    $results[] = "✅ Added updated_at column to IFW_kyc_submissions";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column') || str_contains($e->getMessage(), 'already exists')) {
        $results[] = "ℹ️ updated_at column already exists in IFW_kyc_submissions";
    } else {
        $results[] = "❌ Error: " . $e->getMessage();
    }
}

echo "<h3>Database Upgrade V9 Results</h3>";
echo "<ul>";
foreach ($results as $r) {
    echo "<li>$r</li>";
}
echo "</ul>";
echo "<p><strong>Done!</strong> You can delete this file from your server now.</p>";
?>
