<?php
require_once __DIR__ . '/config/db.php';
$sql = file_get_contents(__DIR__ . '/database/schema.sql');
try {
    $pdo->exec($sql);
    echo "Database schema imported successfully.\n";
} catch (PDOException $e) {
    echo "Error importing database schema: " . $e->getMessage() . "\n";
}
