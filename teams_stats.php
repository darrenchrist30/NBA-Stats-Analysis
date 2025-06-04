<?php
    require_once 'db.php';
    include 'header.php'; 

    $filterTeamSeason = $_GET['team_season'] ?? '';

    // 1. Ambil data unik tahun dari team_season_details di coaches_collection
    $seasonsPipeline = [
        ['$unwind' => '$teams'], 
        ['$unwind' => '$teams.team_season_details'], 
        ['$match' => ['teams.team_season_details.year' => ['$ne' => null, '$exists' => true]]],
        ['$group' => ['_id' => '$teams.team_season_details.year']],
        ['$sort' => ['_id' => -1]]
    ];
    $seasonsResult = $coaches_collection->aggregate($seasonsPipeline)->toArray();
    $teamSeasonsForDropdown = array_map(fn($s) => $s['_id'], $seasonsResult);

    // 2. Bangun pipeline agregasi utama untuk mengambil data tim
    $mainPipeline = [];
    $mainPipeline[] = ['$unwind' => '$teams']; 
    $mainPipeline[] = ['$match' => ['teams.team_season_details' => ['$ne' => null, '$exists' => true]]]; 
    $mainPipeline[] = ['$replaceRoot' => ['newRoot' => '$teams.team_season_details']]; 

    if ($filterTeamSeason && $filterTeamSeason !== '') {
        $mainPipeline[] = ['$match' => ['year' => (int)$filterTeamSeason]];
    }
    
    $mainPipeline[] = [
        '$group' => [
            '_id' => ['year' => '$year', 'tmID' => '$tmID'],
            'doc' => ['$first' => '$$ROOT'] 
        ]
    ];
    $mainPipeline[] = ['$replaceRoot' => ['newRoot' => '$doc']]; 
    $mainPipeline[] = ['$sort' => ['won' => -1, 'lost' => 1]]; 

    $teamsData = $coaches_collection->aggregate($mainPipeline)->toArray();

    function calculateWinPercentage($won, $lost, $games = null) {
        $won = (int)($won ?? 0); $lost = (int)($lost ?? 0); $games = (int)($games ?? 0);
        $totalGamesPlayed = ($games > 0) ? $games : ($won + $lost);
        return $totalGamesPlayed > 0 ? round(($won / $totalGamesPlayed) * 100, 1) : 0;
    }
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Performa Tim NBA (Data dari Pelatih)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Roboto+Condensed:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* (Salin semua style dari kode teams_stats.php lama Anda) */
        body { font-family: 'Inter', sans-serif; background-color: #F0F4F8; color: #1F2937; }
        .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
        .stat-card { background-color: white; border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.06); transition: transform 0.2s, box-shadow 0.2s; padding: 1.25rem; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 16px rgba(0,0,0,0.08); }
        .table-wrapper { background-color: white; border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.06); overflow: hidden;}
        .table-scroll-container { max-height: 70vh; overflow-y: auto; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #e5e7eb; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #6b7280; }
        .chart-container { background-color: white; border-radius: 0.75rem; padding: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        select, input[type="text"] { border-color: #D1D5DB; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        select { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236B7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; padding-right: 2.5rem; -webkit-appearance: none; -moz-appearance: none; appearance: none; }
        input:focus, select:focus { outline: 2px solid transparent; outline-offset: 2px; border-color: #4F46E5; box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.4); }
        .btn { font-weight: 600; padding: 0.625rem 1.25rem; border-radius: 0.375rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.2s; }
        .btn-primary { background-color: #4F46E5; color: white; }
        .btn-primary:hover { background-color: #4338CA; }
        .badge { display: inline-block; padding: 0.25em 0.6em; font-size: 0.75em; font-weight: 600; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.375rem; }
        .badge-green { background-color: #d1fae5; color: #065f46; }
        .badge-red { background-color: #fee2e2; color: #991b1b; }
        .badge-blue { background-color: #dbeafe; color: #1e40af; }
        .badge-yellow { background-color: #fef9c3; color: #854d0e; }
        .badge-gray { background-color: #f3f4f6; color: #4b5563; }
    </style>
</head>
<body class="text-slate-800 antialiased">
    <div class="max-w-screen-2xl mx-auto p-4 md:p-6 lg:p-8">
        <header class="mb-8 text-center md:text-left">
            <h1 class="text-3xl md:text-4xl font-extrabold text-slate-800 font-condensed">Dashboard Performa Tim NBA</h1>
            <p class="text-md text-slate-500 mt-1.5">Analisis statistik tim berdasarkan musim (data dari pelatih).</p>
        </header>

        <!-- Filter Section -->
        <div class="bg-white p-5 md:p-6 rounded-xl shadow-lg mb-8 sticky top-4 z-20 border border-slate-200/75">
            <form method="GET" class="flex flex-col sm:flex-row gap-4 items-center sm:items-end justify-center">
                <div class="w-full sm:w-auto flex-grow">
                    <label for="team_season" class="block text-sm font-medium text-slate-600 mb-1.5">Pilih Musim</label>
                    <select name="team_season" id="team_season" class="w-full border-slate-300 rounded-lg text-sm py-2.5 px-3 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Semua Musim</option>
                        <?php foreach ($teamSeasonsForDropdown as $year): ?>
                            <option value="<?= htmlspecialchars($year) ?>" <?= ((string)$filterTeamSeason === (string)$year) ? 'selected' : '' ?>><?= htmlspecialchars($year) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-full sm:w-auto flex items-center justify-center h-[42px]">
                    <i class="fas fa-filter mr-2 fa-sm"></i> Terapkan Filter
                </button>
            </form>
        </div>

        <!-- Stat Summary Cards -->
        <?php
            // ... (Logika $summaryCards sama seperti sebelumnya, menggunakan $teamsData) ...
            $totalTeamsDisplayed = count($teamsData);
            $totalGamesPlayedOverall = 0;
            $totalWinsOverall = 0;
            $bestPerformingTeam = ['name' => '-', 'win_pct' => 0, 'year' => '-'];
            
            foreach ($teamsData as $team) {
                $games = (int)($team['games'] ?? 0);
                $wins = (int)($team['won'] ?? 0);
                $losses = (int)($team['lost'] ?? 0);
                $currentTotalGames = ($games > 0) ? $games : ($wins + $losses);

                $totalGamesPlayedOverall += $currentTotalGames;
                $totalWinsOverall += $wins;

                $winPct = calculateWinPercentage($wins, $losses, $games);
                if ($winPct > $bestPerformingTeam['win_pct']) {
                    $bestPerformingTeam['name'] = $team['name'] ?? $team['tmID'] ?? '-';
                    $bestPerformingTeam['win_pct'] = $winPct;
                    $bestPerformingTeam['year'] = $team['year'] ?? '-';
                }
            }
            $overallAverageWinPct = $totalGamesPlayedOverall > 0 ? round(($totalWinsOverall / $totalGamesPlayedOverall) * 100, 1) : 0;
            
            $summaryCards = [
                ['label' => 'Total Tim Ditampilkan', 'value' => $totalTeamsDisplayed, 'icon' => 'fa-solid fa-users', 'color' => 'text-blue-600', 'bg' => 'bg-blue-100'],
                ['label' => 'Rata-rata Win % (Agregat)', 'value' => $overallAverageWinPct . '%', 'icon' => 'fa-solid fa-percent', 'color' => 'text-green-600', 'bg' => 'bg-green-100'],
                ['label' => 'Tim Terbaik (Win %)', 'value' => htmlspecialchars($bestPerformingTeam['name']), 'sub_value' => $bestPerformingTeam['win_pct'] . '% (' . $bestPerformingTeam['year'] . ')', 'icon' => 'fa-solid fa-trophy', 'color' => 'text-amber-600', 'bg' => 'bg-amber-100'],
                ['label' => 'Total Pertandingan (Agregat)', 'value' => number_format($totalGamesPlayedOverall), 'icon' => 'fa-solid fa-basketball', 'color' => 'text-purple-600', 'bg' => 'bg-purple-100'],
            ];
        ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <?php foreach ($summaryCards as $card): ?>
            <div class="stat-card p-5 flex items-start space-x-4">
                <div class="flex-shrink-0 p-3.5 rounded-full <?= htmlspecialchars($card['bg']) ?> <?= htmlspecialchars($card['color']) ?>">
                     <i class="<?= htmlspecialchars($card['icon']) ?> fa-fw text-xl"></i>
                </div>
                <div class="flex-grow">
                    <p class="text-sm text-slate-500 font-medium uppercase tracking-wider"><?= htmlspecialchars($card['label']) ?></p>
                    <p class="text-2xl font-semibold <?= htmlspecialchars($card['color']) ?> mt-0.5 font-condensed"><?= $card['value'] ?></p>
                    <?php if(isset($card['sub_value'])): ?>
                        <p class="text-xs text-slate-400"><?= $card['sub_value'] ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>


        <!-- Data Table Section -->
        <div class="table-wrapper p-0 mb-10">
             <div class="px-5 py-4 border-b border-slate-200 flex justify-between items-center">
                 <h2 class="text-xl font-semibold text-slate-700 font-condensed">Detail Performa Tim</h2>
                 <?php if ($totalTeamsDisplayed > 0): ?>
                    <span class="text-xs font-medium text-slate-500">Menampilkan <?= $totalTeamsDisplayed ?> tim unik</span>
                 <?php endif; ?>
            </div>
            <div class="table-scroll-container">
                <table class="min-w-full table-fixed text-sm">
                    <thead class="sticky top-0 bg-slate-100 z-10 shadow-sm">
                        <tr class="text-left text-xs text-slate-500 uppercase tracking-wider">
                            <th class="px-4 py-3 font-semibold w-[8%] text-center">Musim</th>
                            <th class="px-4 py-3 font-semibold w-[22%]">Tim</th>
                            <th class="px-4 py-3 font-semibold w-[6%] text-center">W</th>
                            <th class="px-4 py-3 font-semibold w-[6%] text-center">L</th>
                            <th class="px-4 py-3 font-semibold w-[8%] text-center">Win %</th>
                            <th class="px-4 py-3 font-semibold w-[10%] text-center">Home (W-L)</th>
                            <th class="px-4 py-3 font-semibold w-[10%] text-center">Away (W-L)</th>
                            <th class="px-4 py-3 font-semibold w-[8%] text-center">Rank</th>
                            <th class="px-4 py-3 font-semibold w-[10%] text-center">Playoff</th>
                            <th class="px-4 py-3 font-semibold w-[12%] text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (count($teamsData) > 0): ?>
                            <?php foreach ($teamsData as $idx => $team): 
                                $winPct = calculateWinPercentage($team['won'] ?? 0, $team['lost'] ?? 0, $team['games'] ?? 0);
                                $rowClass = $idx % 2 == 0 ? 'bg-white' : 'bg-slate-50/80';
                                $playoffStatus = $team['playoff'] ?? '-';
                                $playoffBadge = 'badge-gray';
                                $teamYear = $team['year'] ?? '';
                                $teamName = $team['name'] ?? ($team['tmID'] ?? 'N/A'); 
                                $teamTmID = $team['tmID'] ?? '';

                                if (strpos(strtolower($playoffStatus), 'won') !== false || strpos(strtolower($playoffStatus), 'champion') !== false) {
                                    $playoffBadge = 'badge-green';
                                } elseif (strpos(strtolower($playoffStatus), 'lost') !== false || strpos(strtolower($playoffStatus), 'finals') !== false || strpos(strtolower($playoffStatus), 'semifinals') !== false || strpos(strtolower($playoffStatus), 'round') !== false ) {
                                    $playoffBadge = 'badge-yellow';
                                }
                            ?>
                                <tr class="<?= $rowClass ?> hover:bg-blue-50/40 transition-colors duration-100 group">
                                    <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap text-center"><?= htmlspecialchars($teamYear) ?></td>
                                    <td class="px-4 py-2.5 font-medium text-slate-700 whitespace-nowrap truncate group-hover:text-indigo-600">
                                        <a href="team_detail.php?year=<?= htmlspecialchars($teamYear) ?>&tmID=<?= urlencode($teamTmID) ?>" class="hover:underline" title="Detail Statistik <?= htmlspecialchars($teamName) ?>">
                                            <?= htmlspecialchars($teamName) ?> <span class="text-xs text-slate-400">(<?= htmlspecialchars($teamTmID) ?>)</span>
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 text-green-600 font-semibold text-center whitespace-nowrap"><?= htmlspecialchars($team['won'] ?? '0') ?></td>
                                    <td class="px-4 py-2.5 text-red-600 font-semibold text-center whitespace-nowrap"><?= htmlspecialchars($team['lost'] ?? '0') ?></td>
                                    <td class="px-4 py-2.5 text-blue-600 font-bold text-center whitespace-nowrap"><?= $winPct ?>%</td>
                                    <td class="px-4 py-2.5 text-slate-600 text-center whitespace-nowrap"><?= htmlspecialchars($team['homeWon'] ?? '0') ?> - <?= htmlspecialchars($team['homeLost'] ?? '0') ?></td>
                                    <td class="px-4 py-2.5 text-slate-600 text-center whitespace-nowrap"><?= htmlspecialchars($team['awayWon'] ?? '0') ?> - <?= htmlspecialchars($team['awayLost'] ?? '0') ?></td>
                                    <td class="px-4 py-2.5 text-slate-600 text-center whitespace-nowrap">
                                        <?= htmlspecialchars($team['rank'] ?? '-') ?>
                                        <?php
                                            $confID = strtoupper($team['confID'] ?? '');
                                            if ($confID === 'WC') echo ' <span class="badge badge-blue">WC</span>';
                                            elseif ($confID === 'EC') echo ' <span class="badge badge-red">EC</span>';
                                        ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-center whitespace-nowrap">
                                        <span class="badge <?= $playoffBadge ?>"><?= htmlspecialchars($playoffStatus) ?></span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center whitespace-nowrap">
                                        <a href="team_detail.php?year=<?= htmlspecialchars($teamYear) ?>&tmID=<?= urlencode($teamTmID) ?>"
                                           class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold hover:underline py-1 px-1.5 rounded-md transition-colors"
                                           title="Lihat Detail Statistik Tim">
                                            Detail <i class="fas fa-chart-line fa-xs ml-0.5"></i>
                                        </a>
                                        <a href="team_playoff.php?year=<?= htmlspecialchars($teamYear) ?>&tmID=<?= urlencode($teamTmID) ?>"
                                           class="text-purple-600 hover:text-purple-800 text-xs font-semibold hover:underline py-1 px-1.5 rounded-md transition-colors ml-2"
                                           title="Lihat Detail Playoff Tim">
                                            Playoff <i class="fas fa-trophy fa-xs ml-0.5"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="10" class="text-center p-10 text-slate-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-users-slash fa-3x text-slate-300 mb-4 opacity-70"></i>
                                    <p class="font-semibold text-slate-600 text-base">Data Tim Tidak Ditemukan</p>
                                    <p class="text-sm">Silakan sesuaikan filter Anda atau coba musim yang berbeda.</p>
                                </div>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Chart Section -->
         <?php if(count($teamsData) > 0): ?>
        <section class="mt-10 mb-10">
             <div class="px-1 py-4 mb-3">
                <h2 class="text-2xl font-semibold text-slate-700 text-center font-condensed">Visualisasi Performa Tim</h2>
                <p class="text-sm text-slate-500 text-center mt-1.5">Perbandingan persentase kemenangan tim yang ditampilkan (maks. 20).</p>
            </div>
            <div class="grid grid-cols-1 gap-6">
                <div class="chart-container min-h-[400px] md:min-h-[450px]">
                    <h3 class="text-lg font-semibold mb-4 text-slate-600">Win % Tim Teratas</h3>
                    <canvas id="teamWinPercentageChart"></canvas>
                </div>
            </div>
        </section>
        <!-- Chart Tren Tim (membutuhkan penyesuaian cara pengambilan data untuk dropdown dan data tren) -->
        <?php endif; ?>


        <footer class="text-center mt-12 py-6 border-t border-slate-200">
            <p class="text-xs text-slate-500">© <?= date("Y") ?> Dashboard NBA. Data dari berbagai sumber.</p>
        </footer>
    </div>

<script>
    // ... (Salin JavaScript dari versi lengkap teams_stats.php sebelumnya) ...
    // Pastikan $teamsData yang di-encode ke rawTeamsData adalah hasil dari agregasi baru.
    document.addEventListener('DOMContentLoaded', function () {
    const rawTeamsData = <?= json_encode($teamsData); ?>;

    function calculateWinPercentageJS(won, lost, games) {
        won = parseInt(won || 0); lost = parseInt(lost || 0); games = parseInt(games || 0);
        const totalGamesPlayed = (games > 0) ? $games : (won + lost);
        return totalGamesPlayed > 0 ? parseFloat(((won / totalGamesPlayed) * 100).toFixed(1)) : 0;
    }

    const teamWinPctCanvas = document.getElementById('teamWinPercentageChart');
    if (teamWinPctCanvas && rawTeamsData.length > 0) {
        const sortedTeamsForChart = [...rawTeamsData]
            .map(team => ({ ...team, win_pct_calc: calculateWinPercentageJS(team.won, team.lost, team.games) }))
            .sort((a, b) => b.win_pct_calc - a.win_pct_calc)
            .slice(0, 20);

        const labelsBar = sortedTeamsForChart.map(t => `${t.name || t.tmID} (${t.year || 'N/A'})`);
        const dataBar = sortedTeamsForChart.map(t => t.win_pct_calc);

        new Chart(teamWinPctCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labelsBar,
                datasets: [{
                    label: 'Win %', data: dataBar,
                    backgroundColor: dataBar.map(pct => pct >= 50 ? 'rgba(34, 197, 94, 0.75)' : 'rgba(239, 68, 68, 0.75)'),
                    borderColor: dataBar.map(pct => pct >= 50 ? 'rgba(22, 163, 74, 1)' : 'rgba(220, 38, 38, 1)'),
                    borderWidth: 1, borderRadius: { topLeft: 6, topRight: 6 }, barPercentage: 0.7, categoryPercentage: 0.8,
                }]
            },
            options: { 
                responsive: true, maintainAspectRatio: false, indexAxis: sortedTeamsForChart.length > 10 ? 'y' : 'x',
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) { return ` Win %: ${context.parsed.x !== undefined ? context.parsed.x : context.parsed.y}%`;}}}},
                scales: { y: { beginAtZero: true, max: 100, grid: { color: '#e2e8f0'}, ticks:{font:{size:10}}}, x: {max: sortedTeamsForChart.length > 10 ? 100 : undefined, grid: {display: sortedTeamsForChart.length <=10, color: '#e2e8f0'}, ticks:{font:{size:10}, autoSkipPadding:15}}}
            }
        });
    } else if (teamWinPctCanvas) {
        teamWinPctCanvas.parentNode.innerHTML = '<div class="flex flex-col items-center justify-center h-full text-slate-500"><i class="fas fa-chart-bar fa-2x mb-3 text-slate-300"></i><p class="text-sm">Tidak ada data tim untuk ditampilkan pada chart.</p></div>';
    }
    // Chart tren tim masih memerlukan penyesuaian untuk sumber data dan dropdown.
});
</script>
</body>
</html>