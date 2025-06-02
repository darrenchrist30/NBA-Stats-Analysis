<?php
$host = 'localhost';      // Atau IP database server
$dbname = 'nba';          // Ganti dengan nama database kamu
$username = 'root';       // Ganti jika bukan root
$password = '';           // Ganti jika ada password

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Atur error mode ke Exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Jika berhasil, tampilkan pesan
    echo "Koneksi ke database <strong>$dbname</strong> berhasil!";
} catch (PDOException $e) {
    echo "Koneksi gagal: " . $e->getMessage();
    exit;
}
?>
