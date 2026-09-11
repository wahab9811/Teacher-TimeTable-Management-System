<?php
require_once __DIR__ . '/config/db.php';
$sql = file_get_contents(__DIR__ . '/database/schema.sql');
try {
    $pdo->exec($sql);
    
    // Fallback migration for existing databases lacking Friday times
    try {
        $pdo->exec("ALTER TABLE time_slots ADD COLUMN IF NOT EXISTS FridayStartTime TIME NULL, ADD COLUMN IF NOT EXISTS FridayEndTime TIME NULL");
    } catch(PDOException $ex) {
        // Silently ignore if already exists or not supported by DB version
    }
    
    echo "Database schema imported successfully.\n";
} catch (PDOException $e) {
    echo "Error importing database schema: " . $e->getMessage() . "\n";
}
