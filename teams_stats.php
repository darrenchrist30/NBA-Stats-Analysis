<?php
    // Koneksi ke database (pastikan file db.php sudah benar)
    require_once 'db.php';
    include 'header.php'; // Asumsikan header.php ada dan mungkin berisi navigasi

    // Ambil filter tahun dari GET request
    $filterTeamSeason = $_GET['team_season'] ?? '';

    // Ambil data unik tahun dari koleksi teams untuk filter
    // dan urutkan secara descending (tahun terbaru di atas)
    $teamSeasons = $teams->distinct('year');
    if (is_array($teamSeasons)) {
        rsort($teamSeasons); // Urutkan tahun terbaru di atas
    } else {
        $teamSeasons = [];
    }


    // Bangun query untuk tim berdasarkan tahun jika ada filter
    $teamQuery = [];
    if ($filterTeamSeason && $filterTeamSeason !== '') { // Pastikan tidak string kosong
        $teamQuery['year'] = (int)$filterTeamSeason;
    }

    // Ambil data tim dari MongoDB, urutkan berdasarkan Win % tertinggi
    $teamsData = $teams->find($teamQuery, ['sort' => ['won' => -1, 'lost' => 1]])->toArray();

    // Fungsi untuk menghitung persentase kemenangan
    function calculateWinPercentage($won, $lost, $games = null) { // Ubah urutan parameter, games opsional
        $won = (int)($won ?? 0);
        $lost = (int)($lost ?? 0);
        $games = (int)($games ?? 0);

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
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f4f8; /* Latar belakang abu-abu muda netral */
            color: #1a202c; /* Warna teks utama (abu-abu gelap) */
        }
        .stat-card {
            background-color: white;
            border-radius: 0.75rem; /* 12px */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -2px rgba(0, 0, 0, 0.07);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
        }
        .table-container {
            background-color: white;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .table-scroll {
            max-height: 500px; /* Lebih tinggi sedikit untuk tabel */
            overflow-y: auto;
        }
        ::-webkit-scrollbar { width: 6px; height: 6px;}
        ::-webkit-scrollbar-track { background: #e2e8f0; border-radius: 10px;}
        ::-webkit-scrollbar-thumb { background: #a0aec0; border-radius: 10px;}
        ::-webkit-scrollbar-thumb:hover { background: #718096; }
        .chart-container {
            background-color: white;
            border-radius: 0.75rem;
            padding: 1.5rem; /* p-6 */
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        select, input[type="text"] {
            border-color: #cbd5e1; /* gray-300 */
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        input:focus, select:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            border-color: #3b82f6; /* blue-500 */
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
        .btn-primary {
            background-color: #2563eb; /* blue-600 */
            color: white;
            font-weight: 600;
            padding: 0.625rem 1.25rem; /* py-2.5 px-5 */
            border-radius: 0.5rem; /* rounded-lg */
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            transition: background-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .btn-primary:hover {
            background-color: #1d4ed8; /* blue-700 */
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        }
        .btn-primary:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.4);
        }
        .badge {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 0.75em;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.375rem;
        }
        .badge-green { background-color: #d1fae5; color: #065f46; } /* green-100 text-green-700 */
        .badge-red { background-color: #fee2e2; color: #991b1b; } /* red-100 text-red-700 */
        .badge-blue { background-color: #dbeafe; color: #1e40af; } /* blue-100 text-blue-700 */
        .badge-yellow { background-color: #fef9c3; color: #854d0e; } /* yellow-100 text-yellow-700 */
        .badge-gray { background-color: #f3f4f6; color: #4b5563; } /* gray-100 text-gray-600 */
    </style>
</head>

<body class="text-slate-800 antialiased">

    <div class="max-w-screen-xl mx-auto p-4 md:p-6 lg:p-8">

        <header class="mb-8 text-center md:text-left">
            <h1 class="text-3xl md:text-4xl font-extrabold text-slate-800">Dashboard Performa Tim NBA</h1>
            <p class="text-md text-slate-500 mt-1.5">Analisis statistik tim NBA berdasarkan musim.</p>
        </header>

        <!-- Filter Section -->
        <div class="bg-white p-5 md:p-6 rounded-xl shadow-lg mb-8 sticky top-4 z-20 border border-slate-200/75">
            <form method="GET" class="flex flex-col sm:flex-row gap-4 items-center sm:items-end justify-center">
                <div class="w-full sm:w-auto flex-grow">
                    <label for="team_season" class="block text-sm font-medium text-slate-600 mb-1.5">Pilih Musim Tim</label>
                    <select name="team_season" id="team_season" class="w-full border-slate-300 rounded-lg text-sm py-2.5 px-3 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Semua Musim</option>
                        <?php foreach ($teamSeasons as $year): ?>
                            <option value="<?= htmlspecialchars($year) ?>" <?= ((string)$filterTeamSeason === (string)$year) ? 'selected' : '' ?>><?= htmlspecialchars($year) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary w-full sm:w-auto flex items-center justify-center">
                    <i class="fas fa-filter mr-2 fa-sm"></i> Terapkan Filter
                </button>
            </form>
        </div>

        <!-- Stat Summary Cards -->
        <?php
            $totalTeams = count($teamsData);
            $totalGamesPlayedOverall = 0;
            $totalWinsOverall = 0;
            $bestPerformingTeam = ['name' => '-', 'win_pct' => 0, 'year' => '-'];
            $averageGamesPerTeam = 0;

            foreach ($teamsData as $team) {
                $games = (int)($team['games'] ?? 0);
                $wins = (int)($team['won'] ?? 0);
                $losses = (int)($team['lost'] ?? 0);
                $currentTotalGames = ($games > 0) ? $games : ($wins + $losses);

                $totalGamesPlayedOverall += $currentTotalGames;
                $totalWinsOverall += $wins;

                $winPct = calculateWinPercentage($wins, $losses, $games);
                if ($winPct > $bestPerformingTeam['win_pct']) {
                    $bestPerformingTeam['name'] = $team['name'] ?? '-';
                    $bestPerformingTeam['win_pct'] = $winPct;
                    $bestPerformingTeam['year'] = $team['year'] ?? '-';
                }
            }
            $overallAverageWinPct = $totalGamesPlayedOverall > 0 ? round(($totalWinsOverall / $totalGamesPlayedOverall) * 100, 1) : 0;
            if ($totalTeams > 0) {
                $averageGamesPerTeam = round($totalGamesPlayedOverall / $totalTeams, 0);
            }

            $summaryCards = [
                ['label' => 'Total Tim Ditampilkan', 'value' => $totalTeams, 'icon' => 'fa-solid fa-users', 'color' => 'text-blue-600', 'bg' => 'bg-blue-50'],
                ['label' => 'Rata-rata Win %', 'value' => $overallAverageWinPct . '%', 'icon' => 'fa-solid fa-percent', 'color' => 'text-green-600', 'bg' => 'bg-green-50'],
                ['label' => 'Tim Terbaik (Win %)', 'value' => htmlspecialchars($bestPerformingTeam['name']), 'sub_value' => $bestPerformingTeam['win_pct'] . '% (' . $bestPerformingTeam['year'] . ')', 'icon' => 'fa-solid fa-trophy', 'color' => 'text-amber-600', 'bg' => 'bg-amber-50'],
                ['label' => 'Total Pertandingan', 'value' => $totalGamesPlayedOverall, 'icon' => 'fa-solid fa-basketball', 'color' => 'text-purple-600', 'bg' => 'bg-purple-50'],
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
                    <p class="text-2xl font-semibold <?= htmlspecialchars($card['color']) ?> mt-0.5"><?= $card['value'] ?></p>
                    <?php if(isset($card['sub_value'])): ?>
                        <p class="text-xs text-slate-400"><?= $card['sub_value'] ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Data Table Section -->
        <div class="table-container p-0 mb-10">
             <div class="px-5 py-4 border-b border-slate-200 flex justify-between items-center">
                 <h2 class="text-xl font-semibold text-slate-700">Detail Performa Tim</h2>
                 <?php if ($totalTeams > 0): ?>
                    <span class="text-xs font-medium text-slate-500">Menampilkan <?= $totalTeams ?> tim</span>
                 <?php endif; ?>
            </div>
            <div class="overflow-x-auto table-scroll">
                <table class="min-w-full table-auto text-sm">
                    <thead class="sticky top-0 bg-slate-100 z-10">
                        <tr class="text-left text-xs text-slate-500 uppercase tracking-wider">
                            <th class="px-4 py-3 font-semibold">Musim</th>
                            <th class="px-4 py-3 font-semibold">Tim</th>
                            <th class="px-4 py-3 font-semibold text-center">W</th>
                            <th class="px-4 py-3 font-semibold text-center">L</th>
                            <th class="px-4 py-3 font-semibold text-center">Win %</th>
                            <th class="px-4 py-3 font-semibold text-center">Home (W-L)</th>
                            <th class="px-4 py-3 font-semibold text-center">Away (W-L)</th>
                            <th class="px-4 py-3 font-semibold text-center">Rank</th>
                            <th class="px-4 py-3 font-semibold text-center">Playoff</th>
                            <th class="px-4 py-3 font-semibold text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (count($teamsData) > 0): ?>
                            <?php foreach ($teamsData as $idx => $team):
                                $winPct = calculateWinPercentage($team['won'] ?? 0, $team['lost'] ?? 0, $team['games'] ?? 0);
                                $rowClass = $idx % 2 == 0 ? 'bg-white' : 'bg-slate-50/70';
                                $playoffStatus = $team['playoff'] ?? '-';
                                $playoffBadge = 'badge-gray';
                                $teamYear = $team['year'] ?? '';
                                $teamName = $team['name'] ?? 'N/A'; // Nama tim untuk URL
                                $teamTmID = $team['tmID'] ?? '';
                                if (strpos(strtolower($playoffStatus), 'won') !== false || strpos(strtolower($playoffStatus), 'champion') !== false) {
                                    $playoffBadge = 'badge-green';
                                } elseif (strpos(strtolower($playoffStatus), 'lost') !== false || strpos(strtolower($playoffStatus), 'finals') !== false || strpos(strtolower($playoffStatus), 'semifinals') !== false || strpos(strtolower($playoffStatus), 'round') !== false ) {
                                    $playoffBadge = 'badge-yellow';
                                }
                            ?>
                                <tr class="<?= $rowClass ?> hover:bg-blue-50/40 transition-colors duration-100">
                                    <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap text-center"><?= htmlspecialchars($teamYear) ?></td>
                                    <td class="px-4 py-2.5 font-medium text-slate-700 whitespace-nowrap">
                                        <?php // Perbaiki link pada nama tim ?>
                                        <a href="team_detail.php?year=<?= htmlspecialchars($teamYear) ?>&name=<?= urlencode($teamName) ?>" class="hover:text-blue-600 hover:underline">
                                            <?= htmlspecialchars($teamName) ?>
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
                                        <?php // Perbaiki link pada tombol Aksi ?>
                                        <a href="team_detail.php?year=<?= htmlspecialchars($teamYear) ?>&name=<?= urlencode($teamName) ?>"
                                           class="text-blue-600 hover:text-blue-800 text-xs font-semibold hover:underline py-1 px-1.5 rounded-md transition-colors"
                                           title="Lihat Detail Statistik Tim">
                                            Statistik <i class="fas fa-chart-line fa-xs ml-0.5"></i>
                                        </a>
                                        <a href="team_playoff.php?year=<?= htmlspecialchars($teamYear) ?>&name=<?= urlencode($teamName) ?>"
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
                                    <i class="fas fa-basketball fa-3x text-slate-300 mb-4"></i>
                                    <p class="font-semibold text-slate-600">Data Tim Tidak Ditemukan</p>
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
                <h2 class="text-2xl font-semibold text-slate-700 text-center">Visualisasi Performa Tim</h2>
                <p class="text-sm text-slate-500 text-center mt-1.5">Perbandingan persentase kemenangan tim yang ditampilkan.</p>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-1 gap-6"> <!-- Single column for main chart now -->
                <div class="chart-container min-h-[380px] md:min-h-[450px]">
                    <h3 class="text-lg font-semibold mb-4 text-slate-600">Win % Tim Teratas (Maks. 20 Tim)</h3>
                    <canvas id="teamWinPercentageChart"></canvas>
                </div>
            </div>
        </section>

        <section class="mt-10 mb-10">
            <div class="px-1 py-4 mb-3">
                <h2 class="text-2xl font-semibold text-slate-700 text-center">Tren Performa Tim per Musim</h2>
                <p class="text-sm text-slate-500 text-center mt-1.5">Pilih tim untuk melihat tren persentase kemenangan dari tahun ke tahun.</p>
            </div>
            <div class="chart-container min-h-[380px] md:min-h-[450px]">
                <div class="mb-6 flex flex-col sm:flex-row gap-3 items-center justify-center">
                    <label for="teamTrendSelector" class="block text-sm font-medium text-slate-600">Pilih Tim:</label>
                    <select id="teamTrendSelector" class="w-full sm:w-auto border-slate-300 rounded-lg text-sm py-2.5 px-3 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- Pilih Tim --</option>
                        <?php
                        // Ambil semua nama tim unik untuk dropdown tren
                        $allUniqueTeamNames = $teams->distinct('name');
                        if (is_array($allUniqueTeamNames)) {
                            sort($allUniqueTeamNames); // Urutkan nama tim
                            foreach ($allUniqueTeamNames as $teamName):
                                echo '<option value="'.htmlspecialchars($teamName).'">'.htmlspecialchars($teamName).'</option>';
                            endforeach;
                        }
                        ?>
                    </select>
                </div>
                <canvas id="teamTrendChart"></canvas>
            </div>
        </section>
        <?php endif; ?>


        <footer class="text-center mt-12 py-6 border-t border-slate-200">
            <p class="text-xs text-slate-500">© <?= date("Y") ?> Dashboard NBA Tim. Dibuat dengan <i class="fas fa-heart text-red-500"></i>.</p>
        </footer>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const rawTeamsData = <?= json_encode($teamsData); ?>;

    function calculateWinPercentageJS(won, lost, games) {
        won = parseInt(won || 0);
        lost = parseInt(lost || 0);
        games = parseInt(games || 0);
        const totalGamesPlayed = (games > 0) ? games : (won + lost);
        return totalGamesPlayed > 0 ? parseFloat(((won / totalGamesPlayed) * 100).toFixed(1)) : 0;
    }

    // Chart 1: Win % Tim (Bar Chart)
    const teamWinPctCanvas = document.getElementById('teamWinPercentageChart');
    if (teamWinPctCanvas && rawTeamsData.length > 0) {
        // Ambil top 20 tim berdasarkan win% jika data banyak, atau semua jika sedikit
        const sortedTeamsForChart = [...rawTeamsData]
            .map(team => ({
                ...team,
                win_pct_calc: calculateWinPercentageJS(team.won, team.lost, team.games)
            }))
            .sort((a, b) => b.win_pct_calc - a.win_pct_calc);

        const chartDataSubset = sortedTeamsForChart.slice(0, 20);

        const labelsBar = chartDataSubset.map(t => `${t.name || 'N/A'} (${t.year || 'N/A'})`);
        const dataBar = chartDataSubset.map(t => t.win_pct_calc);

        new Chart(teamWinPctCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labelsBar,
                datasets: [{
                    label: 'Win %',
                    data: dataBar,
                    backgroundColor: dataBar.map(pct => pct >= 50 ? 'rgba(22, 163, 74, 0.75)' : 'rgba(220, 38, 38, 0.75)'), // green for >=50%, red for <50%
                    borderColor: dataBar.map(pct => pct >= 50 ? 'rgba(22, 163, 74, 1)' : 'rgba(220, 38, 38, 1)'),
                    borderWidth: 1,
                    borderRadius: { topLeft: 6, topRight: 6 },
                    barPercentage: 0.7,
                    categoryPercentage: 0.8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: rawTeamsData.length > 10 ? 'y' : 'x', // Horizontal bar if many items
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#fff', titleColor: '#1e293b', bodyColor: '#475569',
                        borderColor: '#e2e8f0', borderWidth: 1, padding: 10, cornerRadius: 6,
                        callbacks: {
                            label: function(context) {
                                return ` Win %: ${context.parsed.x !== undefined ? context.parsed.x : context.parsed.y}%`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: { color: '#e2e8f0', drawBorder: false },
                        ticks: { color: '#64748b', font: {size: 10} }
                    },
                    x: {
                        beginAtZero: true,
                        max: rawTeamsData.length > 10 ? 100 : undefined, // Only set max for horizontal bar
                        grid: { display: rawTeamsData.length <= 10, color: '#e2e8f0', drawBorder: false }, // Hide x-grid for horizontal
                        ticks: { color: '#64748b', font: {size: 10}, autoSkipPadding: 15 }
                    }
                }
            }
        });
    } else if (teamWinPctCanvas) {
        teamWinPctCanvas.parentNode.innerHTML = '<div class="flex flex-col items-center justify-center h-full text-slate-500"><i class="fas fa-chart-bar fa-2x mb-3 text-slate-300"></i><p class="text-sm">Tidak ada data untuk ditampilkan pada chart.</p></div>';
    }


    // Chart 2: Tren Performa Tim (Line Chart)
    const teamTrendSelector = document.getElementById('teamTrendSelector');
    const teamTrendCanvas = document.getElementById('teamTrendChart');
    let teamTrendChartInstance;

    async function fetchAllTeamDataForTrend() {
        // Fungsi ini bisa mengambil semua data tim jika diperlukan, atau kita bisa proses dari data yang sudah ada
        // Untuk sekarang, kita akan asumsikan $teams->find([]) akan mengambil semua data tim
        // Ini idealnya adalah request AJAX jika datanya besar
        try {
            const response = await fetch(window.location.pathname + '?getAllTeams=true'); // Tambahkan parameter untuk membedakan request
            if (!response.ok) {
                throw new Error('Network response was not ok for fetching all teams data');
            }
            const allTeamsData = await response.json();
            return allTeamsData;
        } catch (error) {
            console.error("Error fetching all teams data for trend:", error);
            // Fallback to use current page's $teamsData if AJAX fails or not implemented
            // This would require PHP to output JSON if '?getAllTeams=true' is set
            // For now, this example will not implement the AJAX part fully and will just demonstrate client-side.
            // To make this work properly, your PHP needs to handle `$_GET['getAllTeams']`
            // and output ALL teams data as JSON.
            //
            // If `db.php` can be included and $teams collection is available in a separate script:
            // Create a file like `api_get_all_teams.php`
            /*
            <?php
            // api_get_all_teams.php
            require_once 'db.php';
            header('Content-Type: application/json');
            $allTeams = $teams->find([], ['sort' => ['year' => 1]])->toArray();
            echo json_encode($allTeams);
            ?>
            */
            // Then change fetch URL to 'api_get_all_teams.php'
            // For this example, we'll assume `rawTeamsData` contains all data IF no season filter is applied.
            // If a season filter IS applied, this trend chart will only use that season's data.
            // A more robust solution involves AJAX.
            
            // SIMPLIFIED: We'll use a global variable for all teams data if available.
            // This is not ideal for large datasets but works for demonstration.
            // You would need to populate `allTeamsDataForTrendJS` from PHP if you don't use AJAX.
            
            // Let's assume for now this function fetches from an endpoint.
            // If you want to use existing full dataset (if no filter is active), you need to pass it from PHP.
            console.warn("AJAX for all team data not implemented. Trend chart might be limited by current filter.");
            return []; // Return empty to prevent errors, or use filtered data as a fallback
        }
    }


    async function updateTeamTrendChart(selectedTeamName) {
        if (!teamTrendCanvas) return;

        // For a real application, you'd fetch ALL team data here if not already loaded.
        // For this demo, we'll filter `rawTeamsData` if `selectedTeamName` is provided.
        // This means if `rawTeamsData` is already filtered by season, the trend will be limited.
        // A proper implementation fetches all seasons data for the selected team.
        
        let dataForTrend;
        if (selectedTeamName) {
             // This would be where you'd ideally use a complete dataset for ALL years for the selected team
            dataForTrend = rawTeamsData.filter(team => team.name === selectedTeamName);
        } else {
            // If no team is selected, maybe show average win % of all teams per year?
            // This is more complex and might require pre-aggregation or more data processing.
            // For simplicity, we'll just clear the chart if no team is selected after initial load.
            if (teamTrendChartInstance) {
                teamTrendChartInstance.destroy();
                teamTrendCanvas.getContext('2d').clearRect(0, 0, teamTrendCanvas.width, teamTrendCanvas.height);
                 teamTrendCanvas.parentNode.insertAdjacentHTML('beforeend', '<p id="trend-placeholder" class="text-center text-slate-500 text-sm mt-4">Pilih tim untuk melihat tren.</p>');
            }
            return;
        }
         const trendPlaceholder = document.getElementById('trend-placeholder');
         if(trendPlaceholder) trendPlaceholder.remove();


        if (dataForTrend.length === 0 && selectedTeamName) {
             if (teamTrendChartInstance) teamTrendChartInstance.destroy();
            teamTrendCanvas.getContext('2d').clearRect(0, 0, teamTrendCanvas.width, teamTrendCanvas.height);
            teamTrendCanvas.parentNode.insertAdjacentHTML('beforeend', `<p id="trend-placeholder" class="text-center text-slate-500 text-sm mt-4">Tidak ada data tren untuk ${selectedTeamName}.</p>`);
            return;
        }


        const processedTrendData = dataForTrend
            .map(team => ({
                year: parseInt(team.year),
                win_pct: calculateWinPercentageJS(team.won, team.lost, team.games)
            }))
            .sort((a, b) => a.year - b.year);

        const labelsLine = processedTrendData.map(d => d.year.toString());
        const dataLine = processedTrendData.map(d => d.win_pct);

        if (teamTrendChartInstance) {
            teamTrendChartInstance.destroy();
        }

        teamTrendChartInstance = new Chart(teamTrendCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labelsLine,
                datasets: [{
                    label: `Win % Tren untuk ${selectedTeamName}`,
                    data: dataLine,
                    borderColor: '#3b82f6', // blue-500
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#fff',
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'bottom', labels:{ font:{size:11}} },
                    tooltip: {
                        mode: 'index', intersect: false,
                        backgroundColor: '#fff', titleColor: '#1e293b', bodyColor: '#475569',
                        borderColor: '#e2e8f0', borderWidth: 1, padding: 10, cornerRadius: 6,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: { display: true, text: 'Win %', font:{size:12, weight:'500'}},
                        grid: { color: '#e2e8f0', drawBorder: false },
                        ticks: { color: '#64748b', font: {size: 10} }
                    },
                    x: {
                        title: { display: true, text: 'Musim', font:{size:12, weight:'500'}},
                        grid: { display: false },
                        ticks: { color: '#64748b', font: {size: 10} }
                    }
                }
            }
        });
    }

    if (teamTrendSelector) {
        teamTrendSelector.addEventListener('change', function() {
            updateTeamTrendChart(this.value);
        });
        // Initial call to render placeholder or default state
        updateTeamTrendChart("");
    } else if (teamTrendCanvas) {
         teamTrendCanvas.parentNode.innerHTML = '<div class="flex flex-col items-center justify-center h-full text-slate-500"><i class="fas fa-chart-line fa-2x mb-3 text-slate-300"></i><p class="text-sm">Dropdown pemilih tim tidak ditemukan.</p></div>';
    }

});
</script>
</body>
</html>