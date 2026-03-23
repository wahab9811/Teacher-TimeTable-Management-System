<?php
$host = '127.0.0.1';
$db   = 'gcbskp_timetable';
$user = 'root'; // default XAMPP username
$pass = ''; // default XAMPP password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    if ($e->getCode() == 1049) {
        // DB does not exist, let's try creating it (often useful when schema is not yet imported in test scenarios)
        try {
            $pdoInit = new PDO("mysql:host=$host;charset=$charset", $user, $pass, $options);
            $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `$db`");
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e2) {
            throw new \PDOException($e2->getMessage(), (int)$e2->getCode());
        }
    } else {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}
?>
