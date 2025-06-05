<?php
    require_once 'db.php';
    include 'header.php';

    $filterTeamSeasons = [];
    if (isset($_GET['team_seasons']) && is_array($_GET['team_seasons'])) {
        foreach ($_GET['team_seasons'] as $season_value) {
            if (filter_var($season_value, FILTER_VALIDATE_INT) !== false) {
                $filterTeamSeasons[] = (int)$season_value;
            }
        }
    }

    // 1. Ambil data unik tahun (TAHUN AWAL MUSIM) dari database
    $seasonsPipeline = [
        ['$unwind' => '$teams'],
        ['$unwind' => '$teams.team_season_details'],
        ['$match' => [
            'teams.team_season_details.year' => ['$ne' => null, '$exists' => true],
            // Tambahkan batasan tahun di sini jika data di DB Anda memang seharusnya terbatas
            // Misalnya, jika tahun di DB adalah tahun awal musim:
            // 'teams.team_season_details.year' => ['$gte' => 1936, '$lte' => 2010] // Untuk musim yang berakhir 1937-2011
        ]],
        ['$group' => ['_id' => '$teams.team_season_details.year']],
        ['$sort' => ['_id' => -1]]
    ];
    $seasonsResult = $coaches_collection->aggregate($seasonsPipeline)->toArray();
    $availableSeasons = array_map(fn($s) => $s['_id'], $seasonsResult);

    // Jika ada data 2011 (untuk musim berakhir 2012) muncul di $availableSeasons padahal tidak seharusnya,
    // berarti data di MongoDB Anda mungkin memiliki entri untuk tahun 2011.
    // Anda bisa memfilter $availableSeasons di PHP jika query di atas belum cukup:
    $max_start_year_allowed = 2010; // Musim berakhir 2011
    $availableSeasons = array_filter($availableSeasons, fn($year) => $year <= $max_start_year_allowed);
    // Urutkan lagi setelah filter jika perlu
    rsort($availableSeasons);


    // 2. Bangun pipeline agregasi utama
    $mainPipeline = [];
    $mainPipeline[] = ['$unwind' => '$teams'];
    $mainPipeline[] = ['$match' => ['teams.team_season_details' => ['$ne' => null, '$exists' => true]]];
    $mainPipeline[] = ['$replaceRoot' => ['newRoot' => '$teams.team_season_details']];

    if (!empty($filterTeamSeasons)) {
        // Pastikan filter juga mematuhi batasan tahun
        $validFilterSeasons = array_filter($filterTeamSeasons, fn($year) => $year <= $max_start_year_allowed);
        if (!empty($validFilterSeasons)) {
            $mainPipeline[] = ['$match' => ['year' => ['$in' => $validFilterSeasons]]];
        } else {
            // Jika filter tidak valid, jangan tampilkan data apa pun atau tampilkan semua (tergantung preferensi)
             $mainPipeline[] = ['$match' => ['year' => -999]]; // Match yang tidak akan ditemukan
        }
    } else {
        // Jika tidak ada filter, Anda mungkin ingin membatasi default ke rentang yang valid
        // $mainPipeline[] = ['$match' => ['year' => ['$lte' => $max_start_year_allowed]]];
        // atau biarkan menampilkan semua yang ada di DB (namun dropdown hanya akan menunjukkan yang valid)
    }


    $mainPipeline[] = [
        '$group' => [
            '_id' => ['year' => '$year', 'tmID' => '$tmID'],
            'doc' => ['$first' => '$$ROOT']
        ]
    ];
    $mainPipeline[] = ['$replaceRoot' => ['newRoot' => '$doc']];
    $mainPipeline[] = ['$sort' => ['year' => -1, 'won' => -1, 'lost' => 1]];

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
    <title>Dashboard Performa Tim NBA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Roboto+Condensed:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F0F4F8; color: #1F2937; }
        .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
        /* ... (style Anda yang lain) ... */

        .dropdown-checklist { position: relative; display: inline-block; width: 100%; /* atau sesuaikan */ }
        .dropdown-checklist summary {
            list-style: none; /* Hapus panah default */
            cursor: pointer;
            padding: 0.625rem 1rem; /* samakan dengan select */
            border: 1px solid #D1D5DB;
            border-radius: 0.375rem; /* samakan dengan select */
            background-color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem; /* text-sm */
            color: #374151; /* gray-700 */
        }
        .dropdown-checklist summary::after {
            content: '▼'; /* Panah dropdown kustom */
            font-size: 0.8em;
            margin-left: 0.5rem;
        }
        .dropdown-checklist[open] summary::after {
            content: '▲';
        }
        .dropdown-checklist-items {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background-color: white;
            border: 1px solid #D1D5DB;
            border-top: none;
            border-radius: 0 0 0.375rem 0.375rem;
            z-index: 10;
            max-height: 200px;
            overflow-y: auto;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        }
        .dropdown-checklist-items label {
            display: block;
            padding: 0.5rem 1rem;
            cursor: pointer;
            font-size: 0.875rem;
        }
        .dropdown-checklist-items label:hover {
            background-color: #F3F4F6; /* gray-100 */
        }
        .dropdown-checklist-items input[type="checkbox"] {
            margin-right: 0.75rem;
            accent-color: #4F46E5;
        }
        .selected-seasons-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex-grow: 1;
            padding-right: 0.5rem;
        }
        .placeholder-text { color: #6B7280; } /* gray-500 */
    </style>
</head>
<body class="text-slate-800 antialiased">
    <div class="max-w-screen-2xl mx-auto p-4 md:p-6 lg:p-8">
        <header class="mb-8 text-center md:text-left">
            <h1 class="text-3xl md:text-4xl font-extrabold text-slate-800 font-condensed">Dashboard Performa Tim NBA</h1>
            <p class="text-md text-slate-500 mt-1.5">Analisis statistik tim berdasarkan musim.</p>
        </header>

        <!-- Filter Section -->
        <div class="bg-white p-5 md:p-6 rounded-xl shadow-lg mb-8 sticky top-4 z-20 border border-slate-200/75">
            <form method="GET" class="flex flex-col md:flex-row gap-4 md:items-end">
                <div class="w-full md:flex-grow">
                    <label for="team_season_dropdown" class="block text-sm font-medium text-slate-600 mb-1.5">Pilih Musim (Tahun Akhir)</label>
                    <details class="dropdown-checklist" id="team_season_dropdown">
                        <summary>
                            <span class="selected-seasons-text">Pilih musim...</span>
                        </summary>
                        <div class="dropdown-checklist-items">
                            <?php if (empty($availableSeasons)): ?>
                                <div class="p-2 text-sm text-gray-500">Tidak ada musim tersedia.</div>
                            <?php else: ?>
                                <?php foreach ($availableSeasons as $startYear): ?>
                                    <label>
                                        <input type="checkbox" name="team_seasons[]" value="<?= htmlspecialchars($startYear) ?>"
                                            <?= in_array($startYear, $filterTeamSeasons) ? 'checked' : '' ?>
                                            onchange="updateSelectedSeasonsText()">
                                        Musim <?= htmlspecialchars((int)$startYear + 1) ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
                <div class="w-full md:w-auto mt-4 md:mt-0">
                    <button type="submit" class="btn btn-primary w-full flex items-center justify-center h-[42px] md:h-auto md:self-end">
                        <i class="fas fa-filter mr-2 fa-sm"></i> Terapkan Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Stat Summary Cards (Logika PHP sama seperti sebelumnya) -->
        <?php
            $totalTeamsDisplayed = count($teamsData);
            $totalGamesPlayedOverall = 0;
            $totalWinsOverall = 0;
            $bestPerformingTeam = ['name' => '-', 'win_pct' => 0, 'year_display' => '-'];

            if ($totalTeamsDisplayed > 0) {
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
                        $bestPerformingTeam['year_display'] = isset($team['year']) ? (int)$team['year'] + 1 : '-';
                    }
                }
            }
            $overallAverageWinPct = $totalGamesPlayedOverall > 0 ? round(($totalWinsOverall / $totalGamesPlayedOverall) * 100, 1) : 0;

            $summaryCards = [
                ['label' => 'Total Entri Tim Ditampilkan', 'value' => $totalTeamsDisplayed, 'icon' => 'fa-solid fa-users', 'color' => 'text-blue-600', 'bg' => 'bg-blue-100'],
                ['label' => 'Rata-rata Win % (Agregat)', 'value' => $overallAverageWinPct . '%', 'icon' => 'fa-solid fa-percent', 'color' => 'text-green-600', 'bg' => 'bg-green-100'],
                ['label' => 'Performa Puncak (Win %)', 'value' => htmlspecialchars($bestPerformingTeam['name']), 'sub_value' => $bestPerformingTeam['win_pct'] . '% (Musim ' . $bestPerformingTeam['year_display'] . ')', 'icon' => 'fa-solid fa-trophy', 'color' => 'text-amber-600', 'bg' => 'bg-amber-100'],
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


        <!-- Data Table Section (Logika PHP sama seperti sebelumnya) -->
        <div class="table-wrapper p-0 mb-10">
             <div class="px-5 py-4 border-b border-slate-200 flex justify-between items-center">
                 <h2 class="text-xl font-semibold text-slate-700 font-condensed">Detail Performa Tim</h2>
                 <?php if ($totalTeamsDisplayed > 0): ?>
                    <span class="text-xs font-medium text-slate-500">Menampilkan <?= $totalTeamsDisplayed ?> entri tim</span>
                 <?php endif; ?>
            </div>
            <div class="table-scroll-container">
                <table class="min-w-full table-fixed text-sm">
                    <thead class="sticky top-0 bg-slate-100 z-10 shadow-sm">
                        <tr class="text-left text-xs text-slate-500 uppercase tracking-wider">
                            <th class="px-4 py-3 font-semibold w-[8%] text-center">Musim (Akhir)</th>
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
                                $teamStartYear = $team['year'] ?? '';
                                $teamDisplayName = $team['name'] ?? ($team['tmID'] ?? 'N/A');
                                $teamTmID = $team['tmID'] ?? '';

                                if (strpos(strtolower($playoffStatus), 'won') !== false || strpos(strtolower($playoffStatus), 'champion') !== false) {
                                    $playoffBadge = 'badge-green';
                                } elseif (strpos(strtolower($playoffStatus), 'lost') !== false || strpos(strtolower($playoffStatus), 'finals') !== false || strpos(strtolower($playoffStatus), 'semifinals') !== false || strpos(strtolower($playoffStatus), 'round') !== false ) {
                                    $playoffBadge = 'badge-yellow';
                                }
                            ?>
                                <tr class="<?= $rowClass ?> hover:bg-blue-50/40 transition-colors duration-100 group">
                                    <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap text-center">
                                        <?= ($teamStartYear !== '') ? htmlspecialchars((int)$teamStartYear + 1) : '-' ?>
                                    </td>
                                    <td class="px-4 py-2.5 font-medium text-slate-700 whitespace-nowrap truncate group-hover:text-indigo-600">
                                        <a href="team_detail.php?year=<?= htmlspecialchars($teamStartYear) ?>&tmID=<?= urlencode($teamTmID) ?>" class="hover:underline" title="Detail Statistik <?= htmlspecialchars($teamDisplayName) ?>">
                                            <?= htmlspecialchars($teamDisplayName) ?> <span class="text-xs text-slate-400">(<?= htmlspecialchars($teamTmID) ?>)</span>
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
                                        <a href="team_detail.php?year=<?= htmlspecialchars($teamStartYear) ?>&tmID=<?= urlencode($teamTmID) ?>"
                                           class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold hover:underline py-1 px-1.5 rounded-md transition-colors"
                                           title="Lihat Detail Statistik Tim">
                                            Detail <i class="fas fa-chart-line fa-xs ml-0.5"></i>
                                        </a>
                                        <a href="team_playoff.php?year=<?= htmlspecialchars($teamStartYear) ?>&tmID=<?= urlencode($teamTmID) ?>"
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

        <!-- Chart Section (Logika PHP dan JS sama seperti sebelumnya) -->
         <?php if(count($teamsData) > 0 && !empty($filterTeamSeasons) && count($filterTeamSeasons) == 1 ): ?>
        <section class="mt-10 mb-10">
             <div class="px-1 py-4 mb-3">
                <h2 class="text-2xl font-semibold text-slate-700 text-center font-condensed">Visualisasi Performa Tim (Musim <?= htmlspecialchars((int)$filterTeamSeasons[0] + 1) ?>)</h2>
                <p class="text-sm text-slate-500 text-center mt-1.5">Perbandingan persentase kemenangan tim (maks. 20).</p>
            </div>
            <div class="grid grid-cols-1 gap-6">
                <div class="chart-container min-h-[400px] md:min-h-[450px]">
                    <h3 class="text-lg font-semibold mb-4 text-slate-600">Win % Tim Teratas</h3>
                    <canvas id="teamWinPercentageChart"></canvas>
                </div>
            </div>
        </section>
        <?php elseif (count($teamsData) > 0 && (empty($filterTeamSeasons) || count($filterTeamSeasons) > 1)): ?>
            <div class="text-center my-10 p-4 bg-blue-50 border border-blue-200 rounded-md">
                <p class="text-sm text-blue-700">Chart performa tim ditampilkan ketika satu musim spesifik dipilih melalui filter.</p>
            </div>
        <?php endif; ?>


        <footer class="text-center mt-12 py-6 border-t border-slate-200">
            <p class="text-xs text-slate-500">© <?= date("Y") ?> Dashboard NBA.</p>
        </footer>
    </div>

<script>
    function updateSelectedSeasonsText() {
        const checkboxes = document.querySelectorAll('.dropdown-checklist-items input[type="checkbox"]:checked');
        const summaryTextElement = document.querySelector('.dropdown-checklist summary .selected-seasons-text');
        if (checkboxes.length === 0) {
            summaryTextElement.textContent = 'Pilih musim...';
            summaryTextElement.classList.add('placeholder-text');
        } else if (checkboxes.length === 1) {
            summaryTextElement.textContent = checkboxes[0].parentNode.textContent.trim();
            summaryTextElement.classList.remove('placeholder-text');
        } else {
            summaryTextElement.textContent = checkboxes.length + ' musim dipilih';
            summaryTextElement.classList.remove('placeholder-text');
        }
    }
    // Panggil saat halaman dimuat untuk set teks awal jika ada filter dari URL
    document.addEventListener('DOMContentLoaded', function () {
        updateSelectedSeasonsText();

        const rawTeamsData = <?= json_encode($teamsData); ?>;
        const filterSeasonsPHP = <?= json_encode($filterTeamSeasons); ?>;

        function calculateWinPercentageJS(won, lost, games) {
            won = parseInt(won || 0); lost = parseInt(lost || 0); games = parseInt(games || 0);
            const totalGamesPlayed = (games > 0) ? games : (won + lost);
            return totalGamesPlayed > 0 ? parseFloat(((won / totalGamesPlayed) * 100).toFixed(1)) : 0;
        }

        const teamWinPctCanvas = document.getElementById('teamWinPercentageChart');
        if (teamWinPctCanvas && rawTeamsData.length > 0 && filterSeasonsPHP.length === 1) {
            const currentSeasonData = rawTeamsData.filter(team => team.year === filterSeasonsPHP[0]);

            const sortedTeamsForChart = [...currentSeasonData]
                .map(team => ({
                    ...team,
                    win_pct_calc: calculateWinPercentageJS(team.won, team.lost, team.games),
                    display_year: team.year ? parseInt(team.year) + 1 : 'N/A'
                }))
                .sort((a, b) => b.win_pct_calc - a.win_pct_calc)
                .slice(0, 20);

            const labelsBar = sortedTeamsForChart.map(t => `${t.name || t.tmID} (Musim ${t.display_year})`);
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
            // Pesan sudah dihandle di PHP, jadi tidak perlu mengubah innerHTML di sini lagi
            // kecuali jika Anda ingin kontrol penuh dari JS.
        }
    });
</script>
</body>
</html>