<?php
require_once 'db.php';
include 'header.php';

// Ambil filter dari request GET
$filterSeason = $_GET['season'] ?? '';
$filterTeam = $_GET['team'] ?? '';
$filterPos = $_GET['pos'] ?? '';
$filterPlayerName = $_GET['playerName'] ?? '';

// Ambil data unik untuk filter dropdown
$seasons = $playersTeams->distinct('year');
rsort($seasons); // Urutkan musim terbaru di atas

$teamIDs = $playersTeams->distinct('tmID');
$teamDisplayNames = [];
if (!empty($teamIDs)) { // Check if teamIDs is not empty and is an array
    if (is_array($teamIDs)) { // Ensure $teamIDs is an array before using $in
        $teamDetails = $teams->find(['tmID' => ['$in' => $teamIDs]], ['projection' => ['tmID' => 1, 'name' => 1, 'year' => 1]]);
        $latestTeamNameMap = [];
        foreach ($teamDetails as $td) {
            if (!isset($latestTeamNameMap[$td['tmID']]) || $td['year'] > $latestTeamNameMap[$td['tmID']]['year']) {
                $latestTeamNameMap[$td['tmID']] = ['name' => $td['name'], 'year' => $td['year']];
            }
        }
        foreach ($teamIDs as $id) {
            $teamDisplayNames[$id] = $latestTeamNameMap[$id]['name'] ?? $id;
        }
        asort($teamDisplayNames);
    } else {
        // Handle case where $teamIDs is not an array, though distinct should return one
        $teamIDs = []; // Ensure it's an empty array if it was not an array
    }
}


$positions = $players->distinct('pos');
$positions = array_filter($positions, fn($value) => !is_null($value) && $value !== ''); // Hapus null atau string kosong
sort($positions);

// Bangun filter query MongoDB
$query = [];
if ($filterSeason) $query['year'] = (int)$filterSeason;
if ($filterTeam) $query['tmID'] = $filterTeam;

// Ambil data performa dari players_teams
$options = ['limit' => 1000]; // Tingkatkan limit untuk perhitungan akurat

$cursor = $playersTeams->find($query, $options);

// Ambil hasil dan playerID
$data = [];
$playerIDsFromCursor = [];

foreach ($cursor as $item) {
    $playerData = $players->findOne(['playerID' => $item['playerID']]);
    $playerName = getPlayerName($item['playerID'], $players);
    $passesFilters = true;

    if ($filterPos && isset($playerData['pos']) && $playerData['pos'] != $filterPos) {
        $passesFilters = false;
    }

    if ($filterPlayerName && stripos($playerName, $filterPlayerName) === false) {
        $passesFilters = false;
    }

    if ($passesFilters) {
        $data[] = $item;
    }
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
            'totalSteals' => 0,
            'totalBlocks' => 0,
            'totalTurnovers' => 0,
            'totalGames' => 0,
        ];
    }
    $seasonStats[$year]['totalPoints'] += $row['points'] ?? 0;
    $seasonStats[$year]['totalAssists'] += $row['assists'] ?? 0;
    $seasonStats[$year]['totalRebounds'] += $row['rebounds'] ?? 0;
    $seasonStats[$year]['totalSteals'] += $row['steals'] ?? 0;
    $seasonStats[$year]['totalBlocks'] += $row['blocks'] ?? 0;
    $seasonStats[$year]['totalTurnovers'] += $row['turnovers'] ?? 0;
    $seasonStats[$year]['totalGames'] += $row['GP'] ?? 0;
}

// Hitung rata-rata per game
$avgSeasonStats = [];
foreach ($seasonStats as $year => $totals) {
    $avgSeasonStats[$year] = [
        'avgPoints' => $totals['totalGames'] > 0 ? round($totals['totalPoints'] / $totals['totalGames'], 2) : 0,
        'avgAssists' => $totals['totalGames'] > 0 ? round($totals['totalAssists'] / $totals['totalGames'], 2) : 0,
        'avgRebounds' => $totals['totalGames'] > 0 ? round($totals['totalRebounds'] / $totals['totalGames'], 2) : 0,
        'avgSteals' => $totals['totalGames'] > 0 ? round($totals['totalSteals'] / $totals['totalGames'], 2) : 0,
        'avgBlocks' => $totals['totalGames'] > 0 ? round($totals['totalBlocks'] / $totals['totalGames'], 2) : 0,
        'avgSteals' => $totals['totalGames'] > 0 ? round($totals['totalSteals'] / $totals['totalGames'], 2) : 0,
    ];
}


// Fungsi bantu cari nama pemain lengkap dari playerID
function getPlayerName($playerID, $players) {
    $p = $players->findOne(['playerID' => $playerID]);
    if ($p) {
        return trim(($p['firstName'] ?? '') . ' ' . ($p['lastName'] ?? ''));
    }
    return $playerID; // Fallback
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Player Performance Dashboard - NBA Stats</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Montserrat', sans-serif; }
        .stat-card {
            transition: transform 0.15s;
        }
        .stat-card:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 8px 24px 0 rgba(59,130,246,0.15);
        }
        .chart-container {
            background: linear-gradient(135deg, #e0e7ff 0%, #f0fdfa 100%);
            border-radius: 1rem;
            padding: 2rem;
        }
        .table-scroll {
            max-height: 420px;
            overflow-y: auto;
        }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-cyan-50 ">
    <h1 class="text-4xl mt-5 font-extrabold text-blue-900 mb-8 text-center tracking-tight drop-shadow">Evaluasi Performa Pemain NBA</h1>

<<<<<<< HEAD
    <form method="GET" class="mb-8 flex flex-wrap gap-4 items-end justify-center">
        <div>
            <label for="season" class="block font-semibold mb-1">Musim</label>
            <select name="season" id="season" class="border rounded px-3 py-1 shadow">
                <option value="">Semua</option>
                <?php foreach ($seasons as $season): ?>
                    <option value="<?= $season ?>" <?= ($filterSeason == $season) ? 'selected' : '' ?>><?= $season ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="team" class="block font-semibold mb-1">Tim</label>
            <select name="team" id="team" class="border rounded px-3 py-1 shadow">
                <option value="">Semua</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?= htmlspecialchars($team) ?>" <?= ($filterTeam == $team) ? 'selected' : '' ?>><?= htmlspecialchars($team) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="pos" class="block font-semibold mb-1">Posisi</label>
            <select name="pos" id="pos" class="border rounded px-3 py-1 shadow">
                <option value="">Semua</option>
                <?php foreach ($positions as $pos): ?>
                    <option value="<?= htmlspecialchars($pos) ?>" <?= ($filterPos == $pos) ? 'selected' : '' ?>><?= htmlspecialchars($pos) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="playerName" class="block font-semibold mb-1">Nama Pemain</label>
            <input type="text" name="playerName" id="playerName" class="border rounded px-3 py-1 shadow" placeholder="Cari nama pemain" value="<?= htmlspecialchars($filterPlayerName) ?>">
        </div>
=======
    <!-- Main Content Area -->
    <div class="max-w-screen-2xl mx-auto p-4 md:p-6 lg:p-8">

            <header class="mb-6 md:mb-8">
                <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Player Performance Dashboard</h1>
                <p class="text-sm text-slate-500 mt-1">Dive into NBA player statistics with interactive filters and visualizations.</p>
            </header>

            <div class="bg-white p-5 md:p-6 rounded-xl shadow-lg mb-8 sticky top-4 z-20 border border-gray-300">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-x-4 gap-y-4 items-end">
            <div>
                <label for="season" class="block text-xs font-medium text-gray-600 mb-1.5">Season</label>
                <select name="season" id="season" class="w-full border border-gray-400 rounded-md text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 appearance-none">
                    <option value="">All Seasons</option>
                    <?php foreach ($seasons as $season): ?>
                        <option value="<?= htmlspecialchars($season) ?>" <?= ($filterSeason == $season) ? 'selected' : '' ?>><?= htmlspecialchars($season) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="team" class="block text-xs font-medium text-gray-600 mb-1.5">Team</label>
                <select name="team" id="team" class="w-full border border-gray-400 rounded-md text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 appearance-none">
                    <option value="">All Teams</option>
                    <?php foreach ($teamDisplayNames as $tmID => $name): ?>
                        <option value="<?= htmlspecialchars($tmID) ?>" <?= ($filterTeam == $tmID) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="pos" class="block text-xs font-medium text-gray-600 mb-1.5">Position</label>
                <select name="pos" id="pos" class="w-full border border-gray-400 rounded-md text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 appearance-none">
                    <option value="">All Positions</option>
                    <?php foreach ($positions as $pos): ?>
                        <option value="<?= htmlspecialchars($pos) ?>" <?= ($filterPos == $pos) ? 'selected' : '' ?>><?= htmlspecialchars($pos) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="playerName" class="block text-xs font-medium text-gray-600 mb-1.5">Player Name</label>
                <input type="text" name="playerName" id="playerName" class="w-full border border-gray-400 rounded-md text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., LeBron James" value="<?= htmlspecialchars($filterPlayerName) ?>">
            </div>
            <button type="submit" class="btn-primary w-full flex items-center justify-center">
                <i class="fas fa-sliders-h mr-2 fa-sm"></i> Apply Filters
            </button>
        </form>
    </div>
>>>>>>> 26792aa9c0c7a787d31831c4424625bd2af6c8a4

    <!-- Stat Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-6 mb-8 max-w-6xl mx-auto">
        <?php
        // Hitung statistik ringkas untuk summary card
        $totalPoints = $totalAssists = $totalRebounds = $totalFG = $totalFGA = $totalGames = 0;
        $totalSteals = $totalBlocks = $totalTurnovers = 0;
        foreach ($data as $row) {
            $totalPoints += $row['points'] ?? 0;
            $totalAssists += $row['assists'] ?? 0;
            $totalRebounds += $row['rebounds'] ?? 0;
            $totalFG += $row['fgMade'] ?? 0;
            $totalFGA += $row['fgAttempted'] ?? 0;
            $totalGames += $row['GP'] ?? 0;
            $totalSteals += $row['steals'] ?? 0;
            $totalBlocks += $row['blocks'] ?? 0;
            $totalTurnovers += $row['turnovers'] ?? 0;
        }
        $avgPoints = $totalGames > 0 ? round($totalPoints / $totalGames, 2) : 0;
        $avgAssists = $totalGames > 0 ? round($totalAssists / $totalGames, 2) : 0;
        $avgRebounds = $totalGames > 0 ? round($totalRebounds / $totalGames, 2) : 0;
        $fgPercent = $totalFGA > 0 ? round(($totalFG / $totalFGA) * 100, 1) : 0;
        $avgSteals = $totalGames > 0 ? round($totalSteals / $totalGames, 2) : 0;
        $avgBlocks = $totalGames > 0 ? round($totalBlocks / $totalGames, 2) : 0;
        $avgTurnovers = $totalGames > 0 ? round($totalTurnovers / $totalGames, 2) : 0;
        $totalGames = 0;
        $totalSeasons = 0;
        foreach ($data as $row) {
            $totalGames += $row['GP'] ?? 0;
            $totalSeasons++;
        }
        $avgGamePlayed = $totalSeasons > 0 ? round($totalGames / $totalSeasons, 2) : 0;
        ?>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-blue-700 mb-1"><?= $avgPoints ?></span>
            <span class="text-gray-600">Rata-rata Poin/Game</span>
        </div>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-green-600 mb-1"><?= $avgAssists ?></span>
            <span class="text-gray-600">Rata-rata Assist/Game</span>
        </div>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-yellow-600 mb-1"><?= $avgRebounds ?></span>
            <span class="text-gray-600">Rata-rata Rebound/Game</span>
        </div>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-purple-600 mb-1"><?= $avgSteals ?></span>
            <span class="text-gray-600">Rata-rata Steal/Game</span>
        </div>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-orange-600 mb-1"><?= $avgBlocks ?></span>
            <span class="text-gray-600">Rata-rata Block/Game</span>
        </div>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-red-600 mb-1"><?= $avgTurnovers ?></span>
            <span class="text-gray-600">Rata-rata Turnover/Game</span>
        </div>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-cyan-700 mb-1"><?= $avgGamePlayed ?></span>
            <span class="text-gray-600">Rata-rata Game Played/Musim</span>
        </div>
    </div>

    <!-- Data Table -->
    <div class="overflow-x-auto bg-white p-4 rounded-xl shadow-lg table-scroll mb-10">
        <table class="min-w-full table-auto border-collapse border border-gray-200 text-sm">
            <thead>
                <tr class="bg-blue-100 text-blue-900">
                    <th class="border border-gray-200 px-3 py-2">Nama Pemain</th>
                    <th class="border border-gray-200 px-3 py-2">Tim</th>
                    <th class="border border-gray-200 px-3 py-2">Posisi</th>
                    <th class="border border-gray-200 px-3 py-2">Musim</th>
                    <th class="border border-gray-200 px-3 py-2">Poin</th>
                    <th class="border border-gray-200 px-3 py-2">Assist</th>
                    <th class="border border-gray-200 px-3 py-2">Rebound</th>
                    <th class="border border-gray-200 px-3 py-2">Steal</th>
                    <th class="border border-gray-200 px-3 py-2">Block</th>
                    <th class="border border-gray-200 px-3 py-2">Turnover</th>
                    <th class="border border-gray-200 px-3 py-2">FG %</th>
                    <th class="border border-gray-200 px-3 py-2">Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($data) > 0): ?>
                    <?php foreach ($data as $row):
                        $playerName = getPlayerName($row['playerID'], $players);
                        $playerData = $players->findOne(['playerID' => $row['playerID']]);
                        $pos = $playerData['pos'] ?? '-';
                        $fgPercent = 0;
                        if (!empty($row['fgAttempted']) && $row['fgAttempted'] > 0) {
                            $fgPercent = round(($row['fgMade'] / $row['fgAttempted']) * 100, 1);
                        }
                    ?>
                    <tr class="border border-gray-200 hover:bg-blue-50 transition">
                        <td class="border px-3 py-1"><?= htmlspecialchars($playerName) ?></td>
                        <td class="border px-3 py-1"><?= htmlspecialchars($row['tmID'] ?? '-') ?></td>
                        <td class="border px-3 py-1"><?= htmlspecialchars($pos) ?></td>
                        <td class="border px-3 py-1"><?= htmlspecialchars($row['year']) ?></td>
                        <td class="border px-3 py-1"><?= htmlspecialchars($row['points'] ?? '-') ?></td>
                        <td class="border px-3 py-1"><?= htmlspecialchars($row['assists'] ?? '-') ?></td>
                        <td class="border px-3 py-1"><?= htmlspecialchars($row['rebounds'] ?? '-') ?></td>
                        <td class="border px-3 py-1"><?= htmlspecialchars($row['steals'] ?? '-') ?></td>
                        <td class="border px-3 py-1"><?= htmlspecialchars($row['blocks'] ?? '-') ?></td>
                        <td class="border px-3 py-1"><?= htmlspecialchars($row['turnovers'] ?? '-') ?></td>
                        <td class="border px-3 py-1"><?= $fgPercent ?>%</td>
                        <td class="border px-3 py-1 text-center">
                            <a href="player_season_detail.php?playerID=<?= $row['playerID'] ?>&year=<?= $row['year'] ?>" class="text-blue-600 hover:text-blue-900 underline font-semibold">
                                Lihat Detail
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="12" class="text-center p-4 text-gray-500">Data tidak ditemukan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Chart Section -->
    <section class="mt-12 chart-container max-w-5xl mx-auto">
        <h2 class="text-2xl font-bold mb-6 text-blue-900 text-center">Visualisasi Statistik</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <h3 class="text-lg font-semibold mb-2 text-blue-700 text-center">Poin, Assist, Rebound per Pemain</h3>
                <canvas id="barChart" class="mb-4"></canvas>
            </div>
            <div>
                <h3 class="text-lg font-semibold mb-2 text-blue-700 text-center">Radar Statistik Pemain</h3>
                <canvas id="radarChart"></canvas>
            </div>
        </section>
        <?php endif; ?>
         <footer class="text-center mt-12 py-6 border-t border-gray-200">
            <p class="text-xs text-slate-500">© <?= date("Y") ?> NBA Stats Dashboard. All data is for demonstrational purposes.</p>
        </footer>
    </div>

<script>
// Ambil data dari PHP untuk chart
const chartData = <?= json_encode($data); ?>;
const allPlayersData = <?= json_encode($players->find()->toArray()); ?>;

function getFullPlayerName(playerID, playersArray) {
    const player = playersArray.find(p => p.playerID === playerID);
    if (player) {
        return (player.firstName || '') + ' ' + (player.lastName || '');
    }
    return playerID;
}

// Siapkan data untuk bar chart: nama pemain vs poin/assist/rebound
const playerNamesBar = chartData.map(d => getFullPlayerName(d.playerID, allPlayersData));
const pointsDataBar = chartData.map(d => d.points || 0);
const assistsDataBar = chartData.map(d => d.assists || 0);
const reboundsDataBar = chartData.map(d => d.rebounds || 0);
const stealsDataBar = chartData.map(d => d.steals || 0);
const blocksDataBar = chartData.map(d => d.blocks || 0);
const turnoversDataBar = chartData.map(d => d.turnovers || 0);

const barChart = new Chart(ctxBar, {
    type: 'bar',
    data: {
        labels: playerNamesBar,
        datasets: [
            {
                label: 'Points',
                data: pointsDataBar,
                backgroundColor: 'rgba(59,130,246,0.7)',
                borderRadius: 6,
                barPercentage: 0.7,
            },
            {
                label: 'Assists',
                data: assistsDataBar,
                backgroundColor: 'rgba(16,185,129,0.7)',
                borderRadius: 6,
                barPercentage: 0.7,
            },
            {
                label: 'Rebounds',
                data: reboundsDataBar,
                backgroundColor: 'rgba(234,179,8,0.7)',
                borderRadius: 6,
                barPercentage: 0.7,
            },
            {
                label: 'Steals',
                data: stealsDataBar,
                backgroundColor: 'rgba(168,85,247,0.7)',
                borderRadius: 6,
                barPercentage: 0.7,
            },
            {
                label: 'Blocks',
                data: blocksDataBar,
                backgroundColor: 'rgba(251,191,36,0.7)',
                borderRadius: 6,
                barPercentage: 0.7,
            },
            {
                label: 'Turnovers',
                data: turnoversDataBar,
                backgroundColor: 'rgba(239,68,68,0.7)',
                borderRadius: 6,
                barPercentage: 0.7,
            },
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
            tooltip: { enabled: true }
        },
        scales: { 
            y: { beginAtZero: true, grid: { color: '#e5e7eb' } },
            x: { grid: { color: '#e5e7eb' } }
        }
    }
});

// Radar Chart untuk pemain pertama (jika ada)
if (chartData.length > 0) {
    const firstPlayerDataRadar = allPlayersData.find(p => p.playerID === chartData[0].playerID);
    const playerNameRadar = getFullPlayerName(chartData[0].playerID, allPlayersData);
    const radarCtx = document.getElementById('radarChart').getContext('2d');
    if (firstPlayerDataRadar) {
        const radarChart = new Chart(radarCtx, {
            type: 'radar',
            data: {
                labels: ['Points', 'Assists', 'Rebounds', 'Steals', 'Blocks', 'Turnovers'],
                datasets: [{
                    label: playerNameRadar,
                    data: [
                        chartData[0].points || 0,
                        chartData[0].assists || 0,
                        chartData[0].rebounds || 0,
                        firstPlayerDataRadar.steals || 0,
                        firstPlayerDataRadar.blocks || 0,
                        chartData[0].turnovers || 0
                    ],
                    fill: true,
                    backgroundColor: 'rgba(59,130,246,0.2)',
                    borderColor: 'rgba(59,130,246,1)',
                    pointBackgroundColor: 'rgba(59,130,246,1)',
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: true }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        angleLines: { color: '#cbd5e1' },
                        grid: { color: '#cbd5e1' },
                        pointLabels: { font: { size: 14, weight: 'bold' }, color: '#1e293b' }
                    }
                }
            }
        });
    } else {
        document.getElementById('radarChart').innerHTML = '<p class="text-center text-gray-500 mt-8">Data pemain untuk radar chart tidak ditemukan.</p>';
    }
}
</script>
</body>

</html>