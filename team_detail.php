<?php
require_once 'db.php';
// include 'header.php'; // Uncomment jika Anda memiliki header umum

$year_param = $_GET['year'] ?? null;
$tmID_param = $_GET['tmID'] ?? null; 

if (!$year_param || !$tmID_param) {
    die("Parameter tahun (year) dan ID Tim (tmID) diperlukan.");
}

$year_param_int = (int)$year_param;

// 1. Ambil data tim spesifik
$pipelineTeamDetail = [
    ['$unwind' => '$teams'],
    ['$unwind' => '$teams.team_season_details'],
    ['$match' => [
        'teams.team_season_details.year' => $year_param_int, 
        'teams.team_season_details.tmID' => $tmID_param
    ]],
    ['$limit' => 1], 
    ['$replaceRoot' => ['newRoot' => '$teams.team_season_details']] 
];
$resultTeam = $coaches_collection->aggregate($pipelineTeamDetail)->toArray();
$teamData = null; 
$teamNameToDisplay = $tmID_param; 

if (!empty($resultTeam)) {
    $teamData = $resultTeam[0];
    $teamNameToDisplay = $teamData['name'] ?? $tmID_param;
}

if (!$teamData) {
    die("Data tim tidak ditemukan untuk ID Tim ".htmlspecialchars($tmID_param)." musim ".htmlspecialchars($year_param).".");
}

// 2. Ambil Rata-Rata Liga/Semua Tim untuk Musim yang Sama
$leagueAverageStats = null;
$avgPipeline = [
    ['$unwind' => '$teams'],
    ['$unwind' => '$teams.team_season_details'],
    ['$match' => ['teams.team_season_details.year' => $year_param_int]],
    ['$group' => [
        '_id' => '$teams.team_season_details.year',
        'avg_o_pts' => ['$avg' => '$teams.team_season_details.o_pts'],
        'avg_o_reb' => ['$avg' => '$teams.team_season_details.o_reb'],
        'avg_o_asts' => ['$avg' => '$teams.team_season_details.o_asts'],
        'avg_o_stl' => ['$avg' => '$teams.team_season_details.o_stl'],
        'avg_o_blk' => ['$avg' => '$teams.team_season_details.o_blk'],
        'avg_o_to' => ['$avg' => '$teams.team_season_details.o_to'],
        'avg_d_pts' => ['$avg' => '$teams.team_season_details.d_pts'],
        'count' => ['$sum' => 1]
    ]]
];
$leagueAvgResult = $coaches_collection->aggregate($avgPipeline)->toArray();
if (!empty($leagueAvgResult)) {
    $leagueAverageStats = $leagueAvgResult[0];
}

// Menyiapkan data untuk ditampilkan
$offensiveStatsDisplay = [];
$defensiveStatsDisplay = [];
if($teamData){
    foreach ($teamData as $key => $value) {
        if (strpos($key, 'o_') === 0) {
            $offensiveStatsDisplay[ucwords(str_replace('_', ' ', substr($key, 2)))] = $value ?? '-';
        } elseif (strpos($key, 'd_') === 0) {
            $defensiveStatsDisplay[ucwords(str_replace('_', ' ', substr($key, 2)))] = $value ?? '-';
        }
    }
    // Tambahkan persentase jika data tersedia
    if (isset($teamData['o_fga']) && $teamData['o_fga'] > 0) $offensiveStatsDisplay['FG%'] = round(($teamData['o_fgm'] / $teamData['o_fga']) * 100, 1) . '%'; else $offensiveStatsDisplay['FG%'] = 'N/A';
    if (isset($teamData['o_3pa']) && $teamData['o_3pa'] > 0) $offensiveStatsDisplay['3P%'] = round(($teamData['o_3pm'] / $teamData['o_3pa']) * 100, 1) . '%'; else $offensiveStatsDisplay['3P%'] = 'N/A';
    if (isset($teamData['o_fta']) && $teamData['o_fta'] > 0) $offensiveStatsDisplay['FT%'] = round(($teamData['o_ftm'] / $teamData['o_fta']) * 100, 1) . '%'; else $offensiveStatsDisplay['FT%'] = 'N/A';

    if (isset($teamData['d_fga']) && $teamData['d_fga'] > 0) $defensiveStatsDisplay['Opp. FG% (Allowed)'] = round(($teamData['d_fgm'] / $teamData['d_fga']) * 100, 1) . '%'; else $defensiveStatsDisplay['Opp. FG% (Allowed)'] = 'N/A';
    if (isset($teamData['d_3pa']) && $teamData['d_3pa'] > 0) $defensiveStatsDisplay['Opp. 3P% (Allowed)'] = round(($teamData['d_3pm'] / $teamData['d_3pa']) * 100, 1) . '%'; else $defensiveStatsDisplay['Opp. 3P% (Allowed)'] = 'N/A';
}


// Data untuk Chart Perbandingan
$comparisonChartData = null;
if ($teamData && $leagueAverageStats) {
    $comparisonChartData = [
        'labels' => ['Points Scored', 'Rebounds', 'Assists', 'Steals', 'Blocks', 'Turnovers (Lower Better)', 'Points Allowed (Lower Better)'],
        'datasets' => [
            [
                'label' => htmlspecialchars($teamNameToDisplay) . ' (' . $year_param . ')',
                'data' => [
                    $teamData['o_pts'] ?? 0, $teamData['o_reb'] ?? 0, $teamData['o_asts'] ?? 0,
                    $teamData['o_stl'] ?? 0, $teamData['o_blk'] ?? 0, 
                    ($teamData['o_to'] ?? 0) * -1, ($teamData['d_pts'] ?? 0) * -1,
                ],
                'backgroundColor' => 'rgba(79, 70, 229, 0.7)', 'borderColor' => 'rgba(79, 70, 229, 1)', 'borderWidth' => 1
            ],
            [
                'label' => 'Rata-Rata Liga (' . $year_param . ')',
                'data' => [
                    round($leagueAverageStats['avg_o_pts'] ?? 0, 1), round($leagueAverageStats['avg_o_reb'] ?? 0, 1),
                    round($leagueAverageStats['avg_o_asts'] ?? 0, 1), round($leagueAverageStats['avg_o_stl'] ?? 0, 1),
                    round($leagueAverageStats['avg_o_blk'] ?? 0, 1), round(($leagueAverageStats['avg_o_to'] ?? 0) * -1, 1),
                    round(($leagueAverageStats['avg_d_pts'] ?? 0) * -1, 1),
                ],
                'backgroundColor' => 'rgba(209, 213, 219, 0.7)', 'borderColor' => 'rgba(107, 114, 128, 1)', 'borderWidth' => 1
            ]
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Tim: <?= htmlspecialchars($teamNameToDisplay) ?> (<?= $year_param ?>)</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto+Condensed:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F3F4F6; color: #1F2937; padding-top: 1rem; padding-bottom: 2rem;}
        .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
        .container { max-width: 1200px; margin: 0 auto; background-color: white; padding: 1.5rem; border-radius: 1rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); }
        h1.page-main-title { color: #1E3A8A; text-align: center; margin-bottom: 0.5rem; font-family: 'Roboto Condensed', sans-serif; font-size: 2.5rem; }
        p.page-subtitle { text-align: center; color: #4B5563; margin-bottom: 2rem; font-size: 1.125rem; }
        h2.section-title { font-family: 'Roboto Condensed', sans-serif; font-size: 1.75rem; font-weight: 700; color: #1E40AF; margin-top: 2rem; margin-bottom: 1rem; border-bottom: 2px solid #93C5FD; padding-bottom: 0.5rem;}
        .stats-display-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
        .stat-display-item { background-color: #F9FAFB; padding: 0.75rem 1rem; border-radius: 0.5rem; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid transparent; }
        .stat-display-item .label { font-size: 0.875rem; color: #4B5563; }
        .stat-display-item .value { font-size: 1rem; font-weight: 600; color: #1F2937; }
        .offensive-border { border-left-color: #2563EB; } 
        .defensive-border { border-left-color: #DC2626; } 
        .chart-wrapper-comparison { background-color: #F9FAFB; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .back-link { display: inline-block; margin-top: 2.5rem; padding: 0.75rem 1.5rem; background-color: #4f46e5; color: white; border-radius: 0.5rem; font-weight: 600; text-decoration: none; text-align: center; transition: background-color 0.3s ease; }
        .back-link:hover { background-color: #4338ca; }
    </style>
</head>
<body>
    <div class="container">
        <div class="text-center mb-6">
             <a href="teams_stats.php<?= isset($_GET['team_season_filter_value']) ? '?team_season=' . htmlspecialchars($_GET['team_season_filter_value']) : '' ?>" 
               class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline">
               <i class="fas fa-arrow-left mr-1"></i> Kembali ke Dashboard Tim
            </a>
        </div>
        <h1 class="page-main-title">Detail Tim: <?= htmlspecialchars($teamNameToDisplay) ?></h1>
        <p class="page-subtitle">Musim <?= htmlspecialchars($year_param) ?></p>

        <?php if ($teamData): ?>
            <section class="mb-8">
                <h2 class="section-title">Ringkasan Umum Musim</h2>
                <div class="stats-display-grid mt-4">
                    <div class="stat-display-item"><span class="label">Liga</span><span class="value"><?= htmlspecialchars($teamData['lgID'] ?? '-') ?></span></div>
                    <div class="stat-display-item"><span class="label">Nama Tim</span><span class="value"><?= htmlspecialchars($teamData['name'] ?? '-') ?> (<?= htmlspecialchars($teamData['tmID'] ?? '-') ?>)</span></div>
                    <div class="stat-display-item"><span class="label">Peringkat Divisi</span><span class="value"><?= htmlspecialchars($teamData['rank'] ?? '-') ?> (<?= htmlspecialchars($teamData['divID'] ?? '-') ?>)</span></div>
                    <div class="stat-display-item"><span class="label">Peringkat Konf.</span><span class="value"><?= htmlspecialchars($teamData['confRank'] ?? '-') ?> (<?= htmlspecialchars($teamData['confID'] ?? '-') ?>)</span></div>
                    <div class="stat-display-item"><span class="label">Playoff</span><span class="value"><?= htmlspecialchars($teamData['playoff'] ?? 'N/A') ?></span></div>
                    <div class="stat-display-item offensive-border"><span class="label">Menang (Total)</span><span class="value text-green-600"><?= htmlspecialchars($teamData['won'] ?? '-') ?></span></div>
                    <div class="stat-display-item defensive-border"><span class="label">Kalah (Total)</span><span class="value text-red-600"><?= htmlspecialchars($teamData['lost'] ?? '-') ?></span></div>
                    <div class="stat-display-item"><span class="label">Total Game (Record)</span><span class="value"><?= htmlspecialchars(($teamData['won'] ?? 0) + ($teamData['lost'] ?? 0)) ?></span></div>
                     <div class="stat-display-item"><span class="label">Total Game (Field Games)</span><span class="value"><?= htmlspecialchars($teamData['games'] ?? '-') ?></span></div>
                    <div class="stat-display-item"><span class="label">Arena</span><span class="value"><?= htmlspecialchars($teamData['arena'] ?? '-') ?></span></div>
                    <div class="stat-display-item"><span class="label">Penonton</span><span class="value"><?= number_format($teamData['attendance'] ?? 0) ?></span></div>
                </div>
            </section>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <section>
                    <h2 class="section-title">Statistik Ofensif (Total Musim)</h2>
                    <div class="stats-display-grid mt-4">
                        <?php foreach ($offensiveStatsDisplay as $label => $value): ?>
                            <div class="stat-display-item offensive-border">
                                <span class="label"><?= htmlspecialchars($label) ?></span>
                                <span class="value"><?= htmlspecialchars($value) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <section>
                    <h2 class="section-title">Statistik Defensif (Total Musim Lawan)</h2>
                    <div class="stats-display-grid mt-4">
                        <?php foreach ($defensiveStatsDisplay as $label => $value): ?>
                             <div class="stat-display-item defensive-border">
                                <span class="label"><?= htmlspecialchars($label) ?></span>
                                <span class="value"><?= htmlspecialchars($value) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <?php if ($comparisonChartData): ?>
            <section class="mb-8">
                <h2 class="section-title">Perbandingan dengan Rata-Rata Liga (Musim <?= $year_param ?>)</h2>
                <div class="chart-wrapper-comparison mt-4 min-h-[450px] lg:min-h-[500px]">
                    <canvas id="teamComparisonChart"></canvas>
                </div>
            </section>
            <?php else: ?>
                <p class="text-center text-slate-500 my-8 bg-slate-50 p-4 rounded-md">Data rata-rata liga tidak tersedia untuk perbandingan pada musim ini.</p>
            <?php endif; ?>

        <?php else: ?>
            <p class="text-center text-red-500 mt-10 py-10 bg-white rounded-lg shadow">Data detail tim tidak ditemukan untuk parameter yang diberikan.</p>
        <?php endif; ?>
        
        <div class="text-center">
            <a href="teams_stats.php<?= isset($_GET['team_season_filter_dari_teams_stats']) ? '?team_season=' . htmlspecialchars($_GET['team_season_filter_dari_teams_stats']) : '' ?>" class="back-link">
                <i class="fas fa-arrow-left mr-2"></i>Kembali ke Dashboard Tim
            </a>
        </div>
    </div>

    <script>
        const teamDataPHP = <?= json_encode($teamData ?? null) ?>;
        const leagueAverageStatsPHP = <?= json_encode($leagueAverageStats ?? null) ?>;
        const comparisonChartDataJS = <?= json_encode($comparisonChartData ?? null) ?>;

        if (comparisonChartDataJS && document.getElementById('teamComparisonChart')) {
            const ctxComparison = document.getElementById('teamComparisonChart').getContext('2d');
            new Chart(ctxComparison, {
                type: 'bar',
                data: comparisonChartDataJS,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y', 
                    plugins: {
                        legend: { position: 'top', labels: {font: {size: 11}, padding:15, usePointStyle: true, boxWidth:10} },
                        title: { display: true, text: 'Perbandingan Statistik Tim vs Rata-Rata Liga', font: { size: 16, weight:'bold' }, padding:{top:5, bottom:20} },
                        tooltip: {
                            backgroundColor: '#fff', titleColor: '#1e293b', bodyColor: '#475569',
                            borderColor: '#e2e8f0', borderWidth: 1, padding: 10, cornerRadius: 6,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) { label += ': '; }
                                    let value = context.raw;
                                    const statLabel = context.label;
                                    if (statLabel === 'Turnovers (Lower Better)' || statLabel === 'Points Allowed (Lower Better)') {
                                        value = Math.abs(value);
                                    }
                                    label += value.toFixed(1);
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { 
                            title: { display: true, text: 'Nilai Statistik', font:{size:12, weight:'500'} },
                             ticks: {
                                callback: function(value) { return Math.abs(value); },
                                font:{size:10}
                            },
                            grid: { color: '#e2e8f0' }
                        },
                        y: { 
                            ticks: { font: { size: 10 } },
                            grid: { display: false }
                        }
                    }
                }
            });
        } else if (document.getElementById('teamComparisonChart')) {
            document.getElementById('teamComparisonChart').parentNode.innerHTML = '<p class="text-center text-sm text-gray-500 py-8">Tidak ada data yang cukup untuk menampilkan chart perbandingan.</p>';
        }
    </script>
</body>
</html>