<?php
// db_sql.php - File untuk koneksi ke database SQL

$host = 'localhost';
$dbname = 'nba_project'; // Pastikan nama ini sesuai dengan di phpMyAdmin
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// Opsi untuk koneksi PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Membuat koneksi PDO
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset", $user, $pass, $options);
} catch (\PDOException $e) {
    // Jika koneksi gagal, hentikan skrip dan tampilkan pesan error
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
