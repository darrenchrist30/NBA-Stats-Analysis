<?php
// Aktifkan tampilan semua error untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Memulai Proses Migrasi Awards (Mode Debug)</h1>";

// --- Konfigurasi Database SQL ---
$host = 'localhost';
$dbname = 'nba_project'; // <-- PASTIKAN NAMA INI BENAR SESUAI phpMyAdmin
$user = 'root';
$pass = ''; // <-- PASTIKAN PASSWORD BENAR
$charset = 'utf8mb4';

// --- Koneksi ke Database ---
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "<p style='color:green; font-weight:bold;'>✅ Koneksi ke database '$dbname' berhasil.</p><hr>";
} catch (\PDOException $e) {
    // Jika koneksi gagal, hentikan skrip dan tampilkan error
    die("<p style='color:red; font-weight:bold;'>❌ GAGAL KONEKSI DATABASE: " . $e->getMessage() . "</p>");
}

// --- Fungsi untuk memproses dan memasukkan data ---
function importData($pdo, $filename, $recipientType)
{
    echo "<h2>Memproses file: '$filename' untuk tipe '$recipientType'</h2>";

    // DEBUG: Cek apakah file ada
    if (!file_exists($filename)) {
        echo "<p style='color:red; font-weight:bold;'>❌ ERROR: File '$filename' tidak ditemukan. Pastikan file berada di folder yang sama dengan skrip ini.</p>";
        return; // Hentikan fungsi jika file tidak ada
    }
    echo "<p style='color:green;'>✔️ File '$filename' ditemukan.</p>";

    // Baca file JSON
    $json_data = file_get_contents($filename);
    $data_array = json_decode($json_data, true);

    // DEBUG: Cek apakah JSON valid
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<p style='color:red; font-weight:bold;'>❌ ERROR: Format JSON di file '$filename' tidak valid.</p>";
        return;
    }
    echo "<p style='color:green;'>✔️ File JSON '$filename' berhasil dibaca.</p>";

    // Menyiapkan SQL Statement
    $sql = "INSERT INTO awards (year, award_name, recipient_type, recipient_id) VALUES (:year, :award_name, :recipient_type, :recipient_id)";
    $stmt = $pdo->prepare($sql);

    $count = 0;
    $errors = 0;
    // Loop melalui setiap item data
    foreach ($data_array as $index => $item) {
        $recipient_id = null;
        if ($recipientType === 'player') {
            $recipient_id = $item['playerID'] ?? null;
        } elseif ($recipientType === 'coach') {
            $recipient_id = $item['coachID'] ?? null;
        }

        // Cek data penting
        if (isset($item['year']) && isset($item['award']) && $recipient_id !== null) {
            try {
                // Eksekusi insert
                $stmt->execute([
                    ':year' => $item['year'],
                    ':award_name' => $item['award'],
                    ':recipient_type' => $recipientType,
                    ':recipient_id' => $recipient_id
                ]);
                $count++;
            } catch (\PDOException $e) {
                echo "<p style='color:red;'>Gagal memasukkan baris ke-" . ($index + 1) . ": " . $e->getMessage() . "</p>";
                $errors++;
            }
        } else {
            // Jika ada data yang hilang
            echo "<p style='color:orange;'>Melewatkan baris ke-" . ($index + 1) . " karena data tidak lengkap.</p>";
            $errors++;
        }
    }
    echo "<hr><p style='font-weight:bold;'>Selesai memproses '$filename': $count data berhasil diimpor, $errors data gagal/dilewati.</p><hr>";
}

// --- Jalankan Proses Impor ---
importData($pdo, 'awards_players.json', 'player');
importData($pdo, 'awards_coaches.json', 'coach');

echo "<h1>✅ Proses Migrasi Selesai.</h1><p>Silakan periksa tabel 'awards' di phpMyAdmin.</p>";
