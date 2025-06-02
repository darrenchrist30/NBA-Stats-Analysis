<?php
    // Koneksi ke database (pastikan file db.php sudah benar)
    require_once 'db.php';
    include 'header.php';

    // Ambil filter tahun dari GET request
    $filterTeamSeason = $_GET['team_season'] ?? '';

    // Ambil data unik tahun dari koleksi teams untuk filter
    $teamSeasons = $teams->distinct('year');
    sort($teamSeasons);

    // Bangun query untuk tim berdasarkan tahun jika ada filter
    $teamQuery = [];
    if ($filterTeamSeason) {
        $teamQuery['year'] = (int)$filterTeamSeason;
    }

    // Ambil data tim dari MongoDB
    $teamsData = $teams->find($teamQuery)->toArray();

    // Fungsi untuk menghitung persentase kemenangan
function calculateWinPercentage($won, $games, $lost) {
    $totalGames = $games;
    if ($games == 0) {
        $totalGames = $won + $lost;
    }
    return $totalGames > 0 ? round(($won / $totalGames) * 100, 2) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teams Stats - NBA Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;400&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; }
        .stat-card {
            transition: transform 0.15s;
        }
        .stat-card:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 8px 24px 0 rgba(16,185,129,0.15);
        }
        .table-scroll {
            max-height: 420px;
            overflow-y: auto;
        }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-thumb { background: #bbf7d0; border-radius: 4px; }
    </style>
</head>

<body class="bg-gradient-to-br from-green-50 to-cyan-50">

    <h2 class="text-4xl mt-5 font-extrabold text-green-900 mb-6 text-center tracking-tight drop-shadow">Analisis Performa Tim NBA</h2>

    <form method="GET" class="mb-8 flex flex-wrap gap-4 items-end justify-center">
        <div>
            <label for="team_season" class="block font-semibold mb-1">Musim Tim</label>
            <select name="team_season" id="team_season" class="border rounded px-3 py-1 shadow">
                <option value="">Semua Musim</option>
                <?php foreach ($teamSeasons as $year): ?>
                    <option value="<?= $year ?>" <?= ($filterTeamSeason == $year) ? 'selected' : '' ?>><?= $year ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-green-600 text-white rounded px-6 py-2 font-bold shadow hover:bg-green-700 transition">Filter Tim</button>
    </form>

    <!-- Stat Summary Cards -->
    <?php
    // Summary untuk dashboard
    $totalGames = $totalWon = $totalLost = 0;
    $bestTeam = null;
    $bestWinPct = 0;
    foreach ($teamsData as $team) {
        $totalGames += $team['games'] ?? 0;
        $totalWon += $team['won'] ?? 0;
        $totalLost += $team['lost'] ?? 0;
        $winPct = calculateWinPercentage($team['won'], $team['games'], $team['lost']);
        if ($winPct > $bestWinPct) {
            $bestWinPct = $winPct;
            $bestTeam = $team['name'];
        }
    }
    $avgWinPct = ($totalGames > 0) ? round(($totalWon / $totalGames) * 100, 2) : 0;
    ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 max-w-5xl mx-auto">
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-green-700 mb-1"><?= $totalGames ?></span>
            <span class="text-gray-600">Total Games</span>
        </div>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-blue-700 mb-1"><?= $avgWinPct ?>%</span>
            <span class="text-gray-600">Rata-rata Win %</span>
        </div>
        <div class="stat-card bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
            <span class="text-2xl font-bold text-orange-600 mb-1"><?= $bestTeam ? htmlspecialchars($bestTeam) : '-' ?></span>
            <span class="text-gray-600">Best Team (Win %)</span>
        </div>
    </div>

    <!-- Data Table -->
    <div class="overflow-x-auto bg-white p-4 rounded-xl shadow-lg table-scroll mb-10">
        <h3 class="text-lg font-semibold mb-4 text-green-700">Performa Tim per Musim</h3>
        <table class="min-w-full table-auto border-collapse border border-gray-200 text-sm">
            <thead>
                <tr class="bg-green-100 text-green-900">
                    <th class="py-2 px-4 border-b font-semibold text-left">Year</th>
                        <th class="py-2 px-4 border-b font-semibold text-left">Team</th>
                        <th class="py-2 px-4 border-b font-semibold text-left">Wins</th>
                        <th class="py-2 px-4 border-b font-semibold text-left">Losses</th>
                        <th class="py-2 px-4 border-b font-semibold text-left">Win %</th>
                        <th class="py-2 px-4 border-b font-semibold text-left">Home Wins</th>
                        <th class="py-2 px-4 border-b font-semibold text-left">Home Losses</th>
                        <th class="py-2 px-4 border-b font-semibold text-left">Away Wins</th>
                        <th class="py-2 px-4 border-b font-semibold text-left">Away Losses</th>
                        <th class="py-2 px-4 border-b font-semibold text-left">Rank (Conf.)</th>
                        <th class="py-2 px-4 border-b font-semibold text-left">Playoff</th>
                        <th class="py-2 px-4 border-b font-semibold text-left">Detail Statistik</th>
                    <th class="py-2 px-4 border-b font-semibold text-left">Detail Playoff</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($teamsData) > 0): ?>
                    <?php foreach ($teamsData as $team): ?>
                        <tr class="border border-gray-200 hover:bg-green-50 transition">
                            <td class="py-2 px-4 border-b"><?= $team['year'] ?></td>
                                <td class="py-2 px-4 border-b"><?= htmlspecialchars($team['name']) ?></td>
                                <td class="py-2 px-4 border-b"><?= $team['won'] ?></td>
                                <td class="py-2 px-4 border-b"><?= $team['lost'] ?></td>
                                <td class="py-2 px-4 border-b"><?= calculateWinPercentage($team['won'], $team['games'], $team['lost']) ?>%</td>
                                <td class="py-2 px-4 border-b"><?= $team['homeWon'] ?></td>
                                <td class="py-2 px-4 border-b"><?= $team['homeLost'] ?></td>
                                <td class="py-2 px-4 border-b"><?= $team['awayWon'] ?></td>
                                <td class="py-2 px-4 border-b"><?= $team['awayLost'] ?></td>
                                <td class="py-2 px-4 border-b">
                                    <?= $team['rank'] ?? '-' ?>
                                    <?php
                                        if ($team['confID'] === 'WC') {
                                            echo '(WC)';
                                        } elseif ($team['confID'] === 'EC') {
                                            echo '(EC)';
                                        }
                                    ?>
                                </td>
                                <td class="py-2 px-4 border-b text-center"><?= htmlspecialchars($team['playoff'] ?? '-') ?></td>
                                <td class="py-2 px-4 border-b text-center">
                                    <a href="team_detail.php?year=<?= $team['year'] ?>&name=<?= urlencode($team['name']) ?>" class="text-indigo-600 hover:text-indigo-800 font-semibold">Lihat Detail</a>
                                </td>
                                <td class="py-2 px-4 border-b text-center">
                                    <a href="team_playoff.php?year=<?= $team['year'] ?>&name=<?= urlencode($team['name']) ?>" class="text-indigo-600 hover:text-indigo-800 font-semibold">Lihat Detail</a>
                                </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="11" class="text-center p-4 text-gray-500">Data tim tidak ditemukan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Chart Section -->
    <section class="mt-12 bg-gradient-to-br from-green-100 to-cyan-100 rounded-2xl shadow-xl p-8 mx-auto" style="max-width: 75%;">
        <h3 class="text-xl font-bold mb-6 text-green-900 text-center">Visualisasi Win % Tim NBA</h3>
        <canvas id="teamChart" height="120"></canvas>
    </section>

    <!-- Perkembangan dari tahun ke tahun Section -->
    <section class="mt-8 bg-white rounded-xl shadow-md p-6 mx-auto" style="max-width: 75%;">
        <h3 class="text-xl font-semibold mb-4 text-green-700 text-center">Lihat Tren Performa Tim</h3>
        <div class="mb-4 flex justify-center">
            <label for="teamTrend" class="mr-2 font-semibold text-gray-700">Pilih Tim:</label>
            <select id="teamTrend" class="border rounded px-3 py-1 shadow">
                <option value="">Semua Tim</option>
                <?php
                $allTeams = $teams->distinct('name');
                sort($allTeams);
                foreach ($allTeams as $teamName): ?>
                    <option value="<?= htmlspecialchars($teamName) ?>"><?= htmlspecialchars($teamName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <canvas id="trendChart" height="150"></canvas>
        </div>
    </section>

    <div class="text-center mb-12 mt-10">
        <a href="index.php" class="bg-blue-600 text-white px-6 py-3 rounded-xl font-bold text-lg shadow hover:bg-blue-700 transition">Kembali ke Beranda</a>
    </div>

    <footer class="text-center p-6 text-gray-500 mt-10">
        &copy; 2025 NBA Team Dashboard &mdash; by Kamu
    </footer>

    <script>
    // Chart interaktif: Win % per tim
    const teamsData = <?= json_encode($teamsData); ?>;
    const ctx = document.getElementById('teamChart').getContext('2d');
    const labels = teamsData.map(t => t.name + ' (' + t.year + ')');
    const winPct = teamsData.map(t => {
        const totalGames = t.games === 0 ? t.won + t.lost : t.games;
        return totalGames > 0 ? Math.round((t.won / totalGames) * 10000) / 100 : 0;
});

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Win %',
                data: winPct,
                backgroundColor: 'rgba(16,185,129,0.7)',
                borderRadius: 8,
                barPercentage: 0.7,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: true }
            },
            scales: {
                y: { beginAtZero: true, max: 100, grid: { color: '#bbf7d0' } },
                x: { grid: { color: '#bbf7d0' } }
            }
        }
    });

    const teamTrendSelect = document.getElementById('teamTrend');
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    let trendChart;

    function updateTrendChart(selectedTeam) {
        const trendData = teamsData
            .filter(t => selectedTeam === "" || t.name === selectedTeam)
            .sort((a, b) => a.year - b.year)
            .map(t => ({ year: t.year, winPct: t.games > 0 ? Math.round((t.won / (t.games === 0 ? t.won + t.lost : t.games)) * 10000) / 100 : 0 }));

        const years = trendData.map(d => d.year);
        const winPercentages = trendData.map(d => d.winPct);

        if (trendChart) {
            trendChart.destroy();
        }

        trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: years,
                datasets: [{
                    label: selectedTeam ? `Win % Trend untuk ${selectedTeam}` : 'Tren Win % Semua Tim (Rata-rata)',
                    data: winPercentages,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Win %'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Tahun'
                        }
                    }
                },
                plugins: {
                    legend: { display: true },
                    tooltip: { enabled: true }
                }
            }
        });
    }

    teamTrendSelect.addEventListener('change', function() {
        updateTrendChart(this.value);
    });

    // Inisialisasi grafik dengan semua tim (rata-rata) atau kosong
    updateTrendChart("");
</script>
</body>
</html>
