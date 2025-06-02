<?php
require_once 'db.php';

// Fungsi bantu cari nama pemain lengkap dari playerID
function getPlayerName($playerID, $players) {
    $p = $players->findOne(['playerID' => $playerID]);
    if ($p) {
        return ($p['firstName'] ?? '') . ' ' . ($p['lastName'] ?? '');
    }
    return $playerID;
}

// Ambil playerID dan year dari GET request
$playerID = $_GET['playerID'] ?? '';
$year = $_GET['year'] ?? '';

if (!$playerID || !$year) {
    echo "Parameter playerID dan year diperlukan.";
    exit;
}

// Bangun filter query MongoDB
$query = ['playerID' => $playerID, 'year' => (int)$year];

// Ambil data dari players_teams
$cursor = $playersTeams->find($query);

$data = [];
foreach ($cursor as $item) {
    $data[] = $item;
}

// Hitung rata-rata statistik per musim
$seasonStats = [];
foreach ($data as $row) {
    $year = $row['year'];
    if (!isset($seasonStats[$year])) {
        $seasonStats[$year] = [
            'totalPoints' => 0,
            'totalAssists' => 0,
            'totalRebounds' => 0,
            'totalGames' => 0,
        ];
    }
    $seasonStats[$year]['totalPoints'] += $row['points'] ?? 0;
    $seasonStats[$year]['totalAssists'] += $row['assists'] ?? 0;
    $seasonStats[$year]['totalRebounds'] += $row['rebounds'] ?? 0;
    $seasonStats[$year]['totalGames'] += $row['GP'] ?? 0;
}

// Hitung rata-rata per game
$avgSeasonStats = [];
foreach ($seasonStats as $year => $totals) {
    $avgSeasonStats[$year] = [
        'avgPoints' => $totals['totalGames'] > 0 ? round($totals['totalPoints'] / $totals['totalGames'], 2) : 0,
        'avgAssists' => $totals['totalGames'] > 0 ? round($totals['totalAssists'] / $totals['totalGames'], 2) : 0,
        'avgRebounds' => $totals['totalGames'] > 0 ? round($totals['totalRebounds'] / $totals['totalGames'], 2) : 0,
    ];
}

$playerName = getPlayerName($playerID, $players);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Detail Statistik Musim - <?= htmlspecialchars($playerName) ?> - <?= $year ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <h1 class="text-3xl font-bold mb-6">Detail Statistik Musim</h1>
    <h2 class="text-2xl font-semibold mb-4"><?= htmlspecialchars($playerName) ?> - Musim <?= $year ?></h2>

    <div class="overflow-x-auto bg-white p-4 rounded shadow">
        <table class="min-w-full table-auto border-collapse border border-gray-300">
            <thead>
                <tr class="bg-blue-100 text-left">
                    <th class="border border-gray-300 px-3 py-2">Musim</th>
                    <th class="border border-gray-300 px-3 py-2">Rata-Rata Poin per Game</th>
                    <th class="border border-gray-300 px-3 py-2">Rata-Rata Assist per Game</th>
                    <th class="border border-gray-300 px-3 py-2">Rata-Rata Rebound per Game</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($avgSeasonStats) > 0): ?>
                    <?php foreach ($avgSeasonStats as $detailYear => $avg): ?>
                    <tr class="border border-gray-300 hover:bg-gray-50">
                        <td class="border px-3 py-1"><?= $detailYear ?></td>
                        <td class="border px-3 py-1"><?= $avg['avgPoints'] ?></td>
                        <td class="border px-3 py-1"><?= $avg['avgAssists'] ?></td>
                        <td class="border px-3 py-1"><?= $avg['avgRebounds'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center p-4">Data tidak ditemukan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="player_stats.php" class="inline-block mt-4 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
        Kembali ke Daftar Statistik Pemain
    </a>
</body>
</html>