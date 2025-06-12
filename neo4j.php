<?php
// etl_mongo_to_neo4j.php

require_once 'autoload.php'; 

use Laudis\Neo4j\ClientBuilder;
use MongoDB\Client as MongoClient;

// --- KONFIGURASI KONEKSI (Tidak Diubah) ---
$neo4j_uri = 'bolt://localhost:7687';
$neo4j_user = 'neo4j';
$neo4j_password = 'neo4jpwd'; 

$mongo_host = 'localhost:27017';
$mongo_user = 'mongo';
$mongo_password = 'mongo';
$mongo_db_name = 'nba_projek'; 
$mongo_collection_name = 'players_collection'; 
$mongo_uri = "mongodb://${mongo_user}:${mongo_password}@${mongo_host}";

// --- INISIALISASI KONEKSI (Tidak Diubah) ---
echo "Menghubungkan ke database...\n";
try {
    $neo4jClient = ClientBuilder::create()->withDriver('bolt', $neo4j_uri, \Laudis\Neo4j\Authentication\Authenticate::basic($neo4j_user, $neo4j_password))->build();
    $mongoClient = new MongoClient($mongo_uri);
    $playersCollection = $mongoClient->$mongo_db_name->$mongo_collection_name;
    $mongoClient->listDatabases(); 
} catch (Exception $e) {
    die("Koneksi gagal: " . $e->getMessage() . "\n");
}
echo "Koneksi berhasil.\n";


// --- LANGKAH 1: MEMBERSIHKAN DATABASE (Tidak Diubah) ---
echo "Membersihkan database Neo4j dan membuat constraints...\n";
try {
    $neo4jClient->run('MATCH (n) DETACH DELETE n');
    $neo4jClient->run('CREATE CONSTRAINT player_id IF NOT EXISTS FOR (p:Player) REQUIRE p.playerID IS UNIQUE');
    $neo4jClient->run('CREATE CONSTRAINT college_name IF NOT EXISTS FOR (c:College) REQUIRE c.name IS UNIQUE');
    $neo4jClient->run('CREATE CONSTRAINT allstar_year IF NOT EXISTS FOR (asg:AllStarGame) REQUIRE asg.year IS UNIQUE');
    echo "Constraints berhasil dibuat.\n";
} catch (Exception $e) {
    die("Gagal membuat constraints: " . $e->getMessage() . "\n");
}


// --- LANGKAH 2: EKSTRAK DARI MONGODB & LOAD KE NEO4J (BAGIAN YANG DIPERBAIKI) ---
echo "Memulai proses ETL...\n";
$cursor = $playersCollection->find([], [
    'projection' => [
        'playerID' => 1,
        'firstName' => 1,
        'lastName' => 1,
        'college' => 1,
        'allstar_games' => 1 // PERUBAHAN 1: Ambil seluruh objek allstar_games, bukan hanya season_id
    ]
]);

$totalPlayers = 0;
$totalAllstarGames = 0;
$totalColleges = 0;
$firstPlayerProcessed = false; // Flag untuk debugging

foreach ($cursor as $player) {
    if (empty($player['playerID'])) continue;

    // --- PERUBAHAN 2 (Opsional, untuk Debugging): Lihat struktur data pemain pertama ---
    if (!$firstPlayerProcessed) {
        echo "--- DEBUG: Struktur data pemain pertama ---\n";
        // Hilangkan komentar di bawah ini jika Anda ingin melihat struktur data yang diterima skrip
        var_dump($player); 
        echo "----------------------------------------\n";
        $firstPlayerProcessed = true;
    }

    $playerName = trim(($player['firstName'] ?? '') . ' ' . ($player['lastName'] ?? ''));
    if (empty($playerName)) continue;

    // 1. Buat Node Player
    $neo4jClient->run(
        'MERGE (p:Player {playerID: $id}) SET p.name = $name',
        ['id' => $player['playerID'], 'name' => $playerName]
    );
    $totalPlayers++;

    // 2. Buat Node College
    if (!empty($player['college'])) {
        $neo4jClient->run(
            'MATCH (p:Player {playerID: $pID}) MERGE (c:College {name: $collegeName}) MERGE (p)-[:ATTENDED]->(c)',
            ['pID' => $player['playerID'], 'collegeName' => $player['college']]
        );
        $totalColleges++;
    }

    // 3. Buat Node All-Star Game
    // Menggunakan iterator untuk menangani BSONArray dengan benar
    if (isset($player['allstar_games']) && ($player['allstar_games'] instanceof \MongoDB\Model\BSONArray || is_array($player['allstar_games']))) {
        foreach ($player['allstar_games'] as $game) {
            // PERUBAHAN 3: Cek 'season_id' di dalam objek/array $game
            if (empty($game['season_id'])) continue;
            
            $neo4jClient->run(
                'MATCH (p:Player {playerID: $pID}) MERGE (asg:AllStarGame {year: $year}) MERGE (p)-[:WAS_ALLSTAR_IN]->(asg)',
                ['pID' => $player['playerID'], 'year' => (int)$game['season_id']]
            );
            $totalAllstarGames++;
        }
    }
    
    // Progress indicator
    if ($totalPlayers % 100 == 0) {
        echo "Telah memproses " . $totalPlayers . " pemain...\n";
    }
}

echo "=====================================\n";
echo "Proses ETL Selesai!\n";
echo "Total Pemain diproses: " . $totalPlayers . "\n";
echo "Total Hubungan College dibuat: " . $totalColleges . "\n";
echo "Total Hubungan All-Star dibuat: " . $totalAllstarGames . "\n";
echo "=====================================\n";
?>