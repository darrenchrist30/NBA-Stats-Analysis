<?php
include 'koneksi.php'; // koneksi ke database

$tables = ['awards_players', 'awards_coaches', 'coaches', 'draft', 'players', 'players_teams', 'player_allstar', 'series_post', 'teams'];

try {
    foreach ($tables as $table) {
        $stmt = $conn->query("SELECT * FROM $table");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Konversi ke JSON dengan format array
        $json_data = json_encode($data, JSON_PRETTY_PRINT);

        // Simpan ke file per tabel, misal players.json, coaches.json, dll
        file_put_contents($table . '.json', $json_data);

        echo "✅ Data tabel <strong>$table</strong> berhasil disimpan ke file <strong>$table.json</strong>!<br>";
    }
} catch (PDOException $e) {
    echo "❌ Gagal mengambil data: " . $e->getMessage();
}
?>
