<?php
// ============================================================
//  DATABASE CONNECTION
//  Edit credentials to match your local server
//  This file is in .gitignore — never push to GitHub
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'food_rescue');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="padding:20px;font-family:sans-serif;color:red">
                <strong>Database connection failed.</strong><br>
                Check your config/db.php credentials.<br>
                Error: ' . htmlspecialchars($e->getMessage()) . '
                </div>');
        }
    }
    return $pdo;
}
?>
