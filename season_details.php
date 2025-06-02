<?php
require_once 'db.php';

$season = $_GET['season'] ?? null;
if (!$season) {
    echo "Musim tidak valid.";
    exit;
}

// Ambil semua data yang sesuai dengan musim yang dipilih
$query = ['year' => (int)$season];
$options = ['limit' => 1000];
$cursor = $playersTeams->find($query, $options);
$data = iterator_to_array($cursor);

// Hitung rata-rata statistik per musim
$seasonStats = [];
foreach ($data as $row) {
    if (!isset($seasonStats[$season])) {
        $seasonStats[$season] = [
            'totalPoints' => 0,
            'totalAssists' => 0,
            'totalRebounds' => 0,
            'totalGames' => 0,
        ];
    }
    $seasonStats[$season]['totalPoints'] += $row['points'] ?? 0;
    $seasonStats[$season]['totalAssists'] += $row['assists'] ?? 0;
    $seasonStats[$season]['totalRebounds'] += $row['rebounds'] ?? 0;
    $seasonStats[$season]['totalGames'] += $row['GP'] ?? 0;
}

// Hitung rata-rata per game
$avgSeasonStats = [];
if (isset($seasonStats[$season])) {
    $avgSeasonStats[$season] = [
        'avgPoints' => $seasonStats[$season]['totalGames'] > 0 ? round($seasonStats[$season]['totalPoints'] / $seasonStats[$season]['totalGames'], 2) : 0,
        'avgAssists' => $seasonStats[$season]['totalGames'] > 0 ? round($seasonStats[$season]['totalAssists'] / $seasonStats[$season]['totalGames'], 2) : 0,
        'avgRebounds' => $seasonStats[$season]['totalGames'] > 0 ? round($seasonStats[$season]['totalRebounds'] / $seasonStats[$season]['totalGames'], 2) : 0,
    ];
}

// Fungsi bantu cari nama pemain lengkap dari playerID
function getPlayerName($playerID, $players) {
    $p = $players->findOne(['playerID' => $playerID]);
    if ($p) {
        return ($p['firstName'] ?? '') . ' ' . ($p['lastName'] ?? '');
    }
    return $playerID;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Detail Musim NBA - <?= htmlspecialchars($season) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 p-6">
    <h1 class="text-3xl font-bold mb-6">Detail Musim <?= htmlspecialchars($season) ?></h1>

    <?php if (count($avgSeasonStats) > 0): ?>
    <div class="overflow-x-auto bg-white p-4 rounded shadow mt-8">
        <h2 class="text-2xl font-semibold mb-4">Rata-Rata Statistik Musim <?= htmlspecialchars($season) ?></h2>
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
                <?php foreach ($avgSeasonStats as $year => $avg): ?>
                <tr class="border border-gray-300 hover:bg-gray-50">
                    <td class="border px-3 py-1"><?= htmlspecialchars($year) ?></td>
                    <td class="border px-3 py-1"><?= htmlspecialchars($avg['avgPoints']) ?></td>
                    <td class="border px-3 py-1"><?= htmlspecialchars($avg['avgAssists']) ?></td>
                    <td class="border px-3 py-1"><?= htmlspecialchars($avg['avgRebounds']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <section class="mt-8 bg-white p-6 rounded shadow">
        <h2 class="text-2xl font-semibold mb-4">Visualisasi Statistik Musim <?= htmlspecialchars($season) ?></h2>
        <canvas id="barChart" class="mb-8"></canvas>
    </section>

    <script>
    // Ambil data dari PHP untuk chart
    const chartData = <?= json_encode($data); ?>;

    // Siapkan data untuk bar chart: nama pemain vs poin
    const playerNames = chartData.map(d => getPlayerName(d.playerID, <?= json_encode($players->find()->toArray()); ?>));
    const pointsData = chartData.map(d => d.points || 0);

    // Chart Bar
    const ctxBar = document.getElementById('barChart').getContext('2d');
    const barChart = new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: playerNames,
            datasets: [
                {
                    label: 'Points',
                    data: pointsData,
                    backgroundColor: 'rgba(59,130,246,0.7)',
                },
            ]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } }
        }
    });
    </script>

</body>
</html>