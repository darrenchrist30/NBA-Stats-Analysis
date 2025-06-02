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
sort($seasons);

$teams = $playersTeams->distinct('tmID');
sort($teams);

$positions = $players->distinct('pos');
sort($positions);

// Bangun filter query MongoDB
$query = [];
if ($filterSeason) $query['year'] = (int)$filterSeason;
if ($filterTeam) $query['tmID'] = $filterTeam;

// --- Filter berdasarkan nama pemain dan posisi ---
$playerIDsToFilter = null; // Gunakan null untuk menandakan belum ada filter ID pemain

// Filter berdasarkan nama pemain
if ($filterPlayerName) {
    $nameRegex = new MongoDB\BSON\Regex(preg_quote($filterPlayerName, '/'), 'i');
    $matchingPlayersByName = $players->find(
        [
            '$or' => [
                ['firstName' => $nameRegex],
                ['lastName' => $nameRegex],
                // Jika Anda memiliki field gabungan seperti 'fullName', Anda bisa menambahkannya di sini
                // ['$expr' => ['$regexMatch' => ['input' => ['$concat' => ['$firstName', ' ', '$lastName']], 'regex' => $nameRegex]]] // Lebih kompleks
            ]
        ],
        ['projection' => ['playerID' => 1]]
    );
    $ids = [];
    foreach ($matchingPlayersByName as $p) {
        $ids[] = $p['playerID'];
    }

    if (empty($ids)) {
        // Jika nama dicari tapi tidak ada pemain yang cocok, set ID ke nilai yang tidak mungkin ada
        $playerIDsToFilter = ['__NO_PLAYER_FOUND_BY_NAME__'];
    } else {
        $playerIDsToFilter = $ids;
    }
}

// Filter berdasarkan posisi
if ($filterPos) {
    $matchingPlayersByPos = $players->find(
        ['pos' => $filterPos],
        ['projection' => ['playerID' => 1]]
    );
    $idsPos = [];
    foreach ($matchingPlayersByPos as $p) {
        $idsPos[] = $p['playerID'];
    }

    if (empty($idsPos)) {
        // Jika posisi dicari tapi tidak ada pemain yang cocok
        if ($playerIDsToFilter !== null && $playerIDsToFilter[0] === '__NO_PLAYER_FOUND_BY_NAME__') {
            // Nama sudah tidak ketemu, posisi juga tidak, tetap tidak ketemu
            $playerIDsToFilter = ['__NO_PLAYER_FOUND_BY_NAME_AND_POS__'];
        } elseif ($playerIDsToFilter !== null) {
            // Nama ketemu, tapi posisi tidak ada yang cocok dari hasil nama (atau semua pemain)
            // Jadi, ini berarti interseksi kosong
            $playerIDsToFilter = ['__NO_PLAYER_FOUND_BY_POS_INTERSECTION__'];
        } else {
            // Hanya filter posisi, dan tidak ada yang cocok
            $playerIDsToFilter = ['__NO_PLAYER_FOUND_BY_POS__'];
        }
    } else {
        // Ada pemain yang cocok dengan posisi
        if ($playerIDsToFilter !== null && $playerIDsToFilter[0] !== '__NO_PLAYER_FOUND_BY_NAME__') {
            // Jika ada filter nama sebelumnya, lakukan interseksi
            $playerIDsToFilter = array_intersect($playerIDsToFilter, $idsPos);
            if (empty($playerIDsToFilter)) {
                $playerIDsToFilter = ['__NO_PLAYER_FOUND_BY_INTERSECTION__'];
            }
        } elseif ($playerIDsToFilter === null) { // Hanya filter posisi yang aktif
            $playerIDsToFilter = $idsPos;
        }
        // Jika $playerIDsToFilter adalah '__NO_PLAYER_FOUND_BY_NAME__', biarkan saja, karena nama sudah jadi prioritas tidak ketemu
    }
}


// Jika ada filter playerID dari nama atau posisi, tambahkan ke query utama
if ($playerIDsToFilter !== null) {
    $query['playerID'] = ['$in' => array_values(array_unique($playerIDsToFilter))]; // array_values untuk re-index
}
// --- Akhir filter nama dan posisi ---


$options = ['limit' => 1000]; // Tingkatkan limit jika perlu, atau implementasi pagination

$cursor = $playersTeams->find($query, $options);

$data = [];
foreach ($cursor as $item) {
    // Karena filter nama dan posisi sudah di query, tidak perlu cek lagi di sini
    // kecuali jika ada filter yang tidak bisa dilakukan di query MongoDB
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
        // Anda salah ketik di sini, harusnya avgTurnovers
        'avgTurnovers' => $totals['totalGames'] > 0 ? round($totals['totalTurnovers'] / $totals['totalGames'], 2) : 0,
    ];
}


// Fungsi bantu cari nama pemain lengkap dari playerID
function getPlayerName($playerID, $playersCollection)
{ // Ganti nama parameter agar lebih jelas
    $p = $playersCollection->findOne(['playerID' => $playerID]);
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
    <title>Player Stats - NBA Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;400&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
        }

        .stat-card {
            transition: transform 0.15s;
        }

        .stat-card:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 8px 24px 0 rgba(59, 130, 246, 0.15);
        }

        .chart-container {
            background: linear-gradient(135deg, #e0e7ff 0%, #f0fdfa 100%);
            border-radius: 1rem;
            padding: 2rem;
        }

        .table-scroll {
            max-height: 420px;
            /* Anda bisa sesuaikan ini */
            overflow-y: auto;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-blue-50 to-cyan-50 ">
    <h1 class="text-4xl mt-5 font-extrabold text-blue-900 mb-8 text-center tracking-tight drop-shadow">Evaluasi Performa Pemain NBA</h1>

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
        <button type="submit" class="bg-blue-600 text-white rounded px-6 py-2 font-bold shadow hover:bg-blue-700 transition">Filter</button>
    </form>

    <!-- Stat Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-7 gap-6 mb-8 max-w-7xl mx-auto px-4">
        <?php
        $totalPoints = $totalAssists = $totalRebounds = $totalFG = $totalFGA = $totalGamesSummary = 0; // Ganti nama variabel agar tidak konflik
        $totalSteals = $totalBlocks = $totalTurnovers = 0;
        $uniquePlayerSeasons = count($data); // Jumlah baris data yang sudah difilter

        foreach ($data as $row) {
            $totalPoints += $row['points'] ?? 0;
            $totalAssists += $row['assists'] ?? 0;
            $totalRebounds += $row['rebounds'] ?? 0;
            $totalFG += $row['fgMade'] ?? 0;
            $totalFGA += $row['fgAttempted'] ?? 0;
            $totalGamesSummary += $row['GP'] ?? 0; // Gunakan nama variabel berbeda
            $totalSteals += $row['steals'] ?? 0;
            $totalBlocks += $row['blocks'] ?? 0;
            $totalTurnovers += $row['turnovers'] ?? 0;
        }
        $avgPoints = $totalGamesSummary > 0 ? round($totalPoints / $totalGamesSummary, 2) : 0;
        $avgAssists = $totalGamesSummary > 0 ? round($totalAssists / $totalGamesSummary, 2) : 0;
        $avgRebounds = $totalGamesSummary > 0 ? round($totalRebounds / $totalGamesSummary, 2) : 0;
        // $fgPercent = $totalFGA > 0 ? round(($totalFG / $totalFGA) * 100, 1) : 0; // FG% lebih cocok di tabel detail
        $avgSteals = $totalGamesSummary > 0 ? round($totalSteals / $totalGamesSummary, 2) : 0;
        $avgBlocks = $totalGamesSummary > 0 ? round($totalBlocks / $totalGamesSummary, 2) : 0;
        $avgTurnovers = $totalGamesSummary > 0 ? round($totalTurnovers / $totalGamesSummary, 2) : 0;

        // Rata-rata Game Played per musim (atau per entri data)
        $avgGamePlayed = $uniquePlayerSeasons > 0 ? round($totalGamesSummary / $uniquePlayerSeasons, 2) : 0;
        ?>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-blue-700 mb-1"><?= $avgPoints ?></span>
            <span class="text-gray-600">Avg Poin/Game</span>
        </div>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-green-600 mb-1"><?= $avgAssists ?></span>
            <span class="text-gray-600">Avg Assist/Game</span>
        </div>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-yellow-600 mb-1"><?= $avgRebounds ?></span>
            <span class="text-gray-600">Avg Rebound/Game</span>
        </div>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-purple-600 mb-1"><?= $avgSteals ?></span>
            <span class="text-gray-600">Avg Steal/Game</span>
        </div>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-orange-600 mb-1"><?= $avgBlocks ?></span>
            <span class="text-gray-600">Avg Block/Game</span>
        </div>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-red-600 mb-1"><?= $avgTurnovers ?></span>
            <span class="text-gray-600">Avg Turnover/Game</span>
        </div>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-cyan-700 mb-1"><?= $avgGamePlayed ?></span>
            <span class="text-gray-600">Avg Game/Entri</span>
        </div>
    </div>

    <!-- Data Table -->
    <div class="max-w-7xl mx-auto px-4">
        <div class="overflow-x-auto bg-white p-4 rounded-xl shadow-lg table-scroll mb-10">
            <table class="min-w-full table-auto border-collapse border border-gray-200 text-sm">
                <thead>
                    <tr class="bg-blue-100 text-blue-900">
                        <th class="border border-gray-200 px-3 py-2">Nama Pemain</th>
                        <th class="border border-gray-200 px-3 py-2">Tim</th>
                        <th class="border border-gray-200 px-3 py-2">Posisi</th>
                        <th class="border border-gray-200 px-3 py-2">Musim</th>
                        <th class="border border-gray-200 px-3 py-2">GP</th>
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
                            $playerData = $players->findOne(['playerID' => $row['playerID']]); // Ambil data pemain untuk posisi
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
                                <td class="border px-3 py-1"><?= htmlspecialchars($row['GP'] ?? '-') ?></td>
                                <td class="border px-3 py-1"><?= htmlspecialchars($row['points'] ?? '-') ?></td>
                                <td class="border px-3 py-1"><?= htmlspecialchars($row['assists'] ?? '-') ?></td>
                                <td class="border px-3 py-1"><?= htmlspecialchars($row['rebounds'] ?? '-') ?></td>
                                <td class="border px-3 py-1"><?= htmlspecialchars($row['steals'] ?? '-') ?></td>
                                <td class="border px-3 py-1"><?= htmlspecialchars($row['blocks'] ?? '-') ?></td>
                                <td class="border px-3 py-1"><?= htmlspecialchars($row['turnovers'] ?? '-') ?></td>
                                <td class="border px-3 py-1"><?= $fgPercent ?>%</td>
                                <td class="border px-3 py-1 text-center">
                                    <a href="player_season_detail.php?playerID=<?= urlencode($row['playerID']) ?>&year=<?= $row['year'] ?>" class="text-blue-600 hover:text-blue-900 underline font-semibold">
                                        Lihat Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="13" class="text-center p-4 text-gray-500">Data tidak ditemukan. Harap sesuaikan filter Anda.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Chart Section -->
    <section class="mt-12 chart-container max-w-5xl mx-auto">
        <h2 class="text-2xl font-bold mb-6 text-blue-900 text-center">Visualisasi Statistik</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <h3 class="text-lg font-semibold mb-2 text-blue-700 text-center">Statistik Pemain (Total per Entri)</h3>
                <canvas id="barChart" class="mb-4"></canvas>
            </div>
            <div>
                <h3 class="text-lg font-semibold mb-2 text-blue-700 text-center">Radar Statistik Pemain (Entri Pertama)</h3>
                <canvas id="radarChart"></canvas>
            </div>
        </div>
    </section>

    <script>
        // Ambil data dari PHP untuk chart
        const chartData = <?= json_encode(array_values($data)); // array_values untuk memastikan array JS 
                            ?>; // Gunakan data yang sudah difilter
        const allPlayersInfo = <?= json_encode($players->find()->toArray()); ?>; // Untuk getPlayerName di JS jika perlu, tapi sebaiknya nama sudah ada

        function getFullPlayerNameJS(playerID, playersArray) {
            const player = playersArray.find(p => p.playerID === playerID);
            if (player) {
                return (player.firstName || '') + ' ' + (player.lastName || '');
            }
            return playerID;
        }

        // Siapkan data untuk bar chart
        // Kita perlu nama pemain untuk setiap entri di chartData
        const playerNamesForChart = chartData.map(d => getFullPlayerNameJS(d.playerID, allPlayersInfo) + ' (' + d.year + ')');
        const pointsDataBar = chartData.map(d => d.points || 0);
        const assistsDataBar = chartData.map(d => d.assists || 0);
        const reboundsDataBar = chartData.map(d => d.rebounds || 0);
        const stealsDataBar = chartData.map(d => d.steals || 0);
        const blocksDataBar = chartData.map(d => d.blocks || 0);
        const turnoversDataBar = chartData.map(d => d.turnovers || 0);

        const ctxBar = document.getElementById('barChart').getContext('2d'); // Definisikan ctxBar
        const barChart = new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: playerNamesForChart,
                datasets: [{
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
                        backgroundColor: 'rgba(168,85,247,0.7)', // Ungu
                        borderRadius: 6,
                        barPercentage: 0.7,
                    },
                    {
                        label: 'Blocks',
                        data: blocksDataBar,
                        backgroundColor: 'rgba(249, 115, 22, 0.7)', // Oranye
                        borderRadius: 6,
                        barPercentage: 0.7,
                    },
                    {
                        label: 'Turnovers',
                        data: turnoversDataBar,
                        backgroundColor: 'rgba(239,68,68,0.7)', // Merah
                        borderRadius: 6,
                        barPercentage: 0.7,
                    },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        enabled: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#e5e7eb'
                        }
                    },
                    x: {
                        grid: {
                            color: '#e5e7eb'
                        }
                    }
                }
            }
        });

        // Radar Chart untuk pemain pertama dalam data yang difilter (jika ada)
        const radarCtx = document.getElementById('radarChart').getContext('2d');
        if (chartData.length > 0) {
            const firstPlayerSeasonData = chartData[0];
            const playerNameRadar = getFullPlayerNameJS(firstPlayerSeasonData.playerID, allPlayersInfo) + ' (' + firstPlayerSeasonData.year + ')';

            new Chart(radarCtx, { // Tidak perlu menyimpan ke variabel jika tidak diakses lagi
                type: 'radar',
                data: {
                    labels: ['Points', 'Assists', 'Rebounds', 'Steals', 'Blocks', 'Turnovers'],
                    datasets: [{
                        label: playerNameRadar,
                        data: [
                            firstPlayerSeasonData.points || 0,
                            firstPlayerSeasonData.assists || 0,
                            firstPlayerSeasonData.rebounds || 0,
                            firstPlayerSeasonData.steals || 0, // Ambil dari data musim ini
                            firstPlayerSeasonData.blocks || 0, // Ambil dari data musim ini
                            firstPlayerSeasonData.turnovers || 0
                        ],
                        fill: true,
                        backgroundColor: 'rgba(59,130,246,0.2)',
                        borderColor: 'rgba(59,130,246,1)',
                        pointBackgroundColor: 'rgba(59,130,246,1)',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }, // Tampilkan legend untuk radar
                        tooltip: {
                            enabled: true
                        }
                    },
                    scales: {
                        r: {
                            beginAtZero: true,
                            angleLines: {
                                color: '#cbd5e1'
                            },
                            grid: {
                                color: '#cbd5e1'
                            },
                            pointLabels: {
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                },
                                color: '#1e293b'
                            } // Sesuaikan ukuran font jika perlu
                        }
                    }
                }
            });
        } else {
            radarCtx.canvas.parentElement.innerHTML = '<p class="text-center text-gray-500 mt-8">Tidak ada data untuk ditampilkan pada Radar Chart.</p>';
            ctxBar.canvas.parentElement.innerHTML = '<p class="text-center text-gray-500 mt-8">Tidak ada data untuk ditampilkan pada Bar Chart.</p>';
        }
    </script>
</body>

</html>