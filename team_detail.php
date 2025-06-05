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

// 2. Ambil Rata-Rata Liga/Semua Tim untuk Musim yang Sama (Ini sudah ada, kita akan gunakan sebagian untuk radar)
$leagueAverageStats = null;
$avgPipeline = [
    ['$unwind' => '$teams'],
    ['$unwind' => '$teams.team_season_details'],
    ['$match' => ['teams.team_season_details.year' => $year_param_int]],
    ['$group' => [
        '_id' => '$teams.team_season_details.year',
        'avg_o_pts' => ['$avg' => '$teams.team_season_details.o_pts'],
        'avg_o_fgm' => ['$avg' => '$teams.team_season_details.o_fgm'], // Tambah untuk radar
        'avg_o_fga' => ['$avg' => '$teams.team_season_details.o_fga'], // Tambah untuk radar
        'avg_o_3pm' => ['$avg' => '$teams.team_season_details.o_3pm'], // Tambah untuk radar
        'avg_o_3pa' => ['$avg' => '$teams.team_season_details.o_3pa'], // Tambah untuk radar
        'avg_o_ftm' => ['$avg' => '$teams.team_season_details.o_ftm'], // Tambah untuk radar
        'avg_o_fta' => ['$avg' => '$teams.team_season_details.o_fta'], // Tambah untuk radar
        'avg_o_reb' => ['$avg' => '$teams.team_season_details.o_reb'],
        'avg_o_asts' => ['$avg' => '$teams.team_season_details.o_asts'],
        'avg_o_stl' => ['$avg' => '$teams.team_season_details.o_stl'],
        'avg_o_blk' => ['$avg' => '$teams.team_season_details.o_blk'],
        'avg_o_to' => ['$avg' => '$teams.team_season_details.o_to'],
        // Defensive averages (allowed by opponents)
        'avg_d_pts' => ['$avg' => '$teams.team_season_details.d_pts'],
        'avg_d_fgm' => ['$avg' => '$teams.team_season_details.d_fgm'], // Tambah untuk radar
        'avg_d_fga' => ['$avg' => '$teams.team_season_details.d_fga'], // Tambah untuk radar
        'avg_d_3pm' => ['$avg' => '$teams.team_season_details.d_3pm'], // Tambah untuk radar
        'avg_d_3pa' => ['$avg' => '$teams.team_season_details.d_3pa'], // Tambah untuk radar
        'avg_d_reb' => ['$avg' => '$teams.team_season_details.d_reb'], // Tambah untuk radar
        'avg_d_asts' => ['$avg' => '$teams.team_season_details.d_asts'], // Tambah untuk radar
        'avg_d_stl' => ['$avg' => '$teams.team_season_details.d_stl'], // Tambah untuk radar (stl lawan = forced by team)
        'avg_d_blk' => ['$avg' => '$teams.team_season_details.d_blk'], // Tambah untuk radar (blk lawan = forced by team)
        'avg_d_to' => ['$avg' => '$teams.team_season_details.d_to'],   // Tambah untuk radar (to lawan = forced by team)
        'count' => ['$sum' => 1]
    ]]
];
$leagueAvgResult = $coaches_collection->aggregate($avgPipeline)->toArray();
if (!empty($leagueAvgResult)) {
    $leagueAverageStats = $leagueAvgResult[0];
}


// ---- PERSIAPAN DATA UNTUK RADAR CHARTS ----
$offensiveRadarData = null;
$defensiveRadarData = null;

if ($teamData && $leagueAverageStats) {
    // Statistik ofensif untuk radar (nilai lebih tinggi lebih baik)
    $offensiveRadarLabels = ['Points', 'FG%', '3P%', 'FT%', 'Rebounds', 'Assists', 'Steals', 'Blocks'];
    $teamOffensiveValues = [
        $teamData['o_pts'] ?? 0,
        isset($teamData['o_fga']) && $teamData['o_fga'] > 0 ? round(($teamData['o_fgm'] / $teamData['o_fga']) * 100, 1) : 0,
        isset($teamData['o_3pa']) && $teamData['o_3pa'] > 0 ? round(($teamData['o_3pm'] / $teamData['o_3pa']) * 100, 1) : 0,
        isset($teamData['o_fta']) && $teamData['o_fta'] > 0 ? round(($teamData['o_ftm'] / $teamData['o_fta']) * 100, 1) : 0,
        $teamData['o_reb'] ?? 0,
        $teamData['o_asts'] ?? 0,
        $teamData['o_stl'] ?? 0,
        $teamData['o_blk'] ?? 0,
        // ($teamData['o_to'] ?? 0) // TO lebih rendah lebih baik, mungkin tidak ideal di radar ofensif ini
    ];
    $leagueAvgOffensiveValues = [
        round($leagueAverageStats['avg_o_pts'] ?? 0, 1),
        isset($leagueAverageStats['avg_o_fga']) && $leagueAverageStats['avg_o_fga'] > 0 ? round(($leagueAverageStats['avg_o_fgm'] / $leagueAverageStats['avg_o_fga']) * 100, 1) : 0,
        isset($leagueAverageStats['avg_o_3pa']) && $leagueAverageStats['avg_o_3pa'] > 0 ? round(($leagueAverageStats['avg_o_3pm'] / $leagueAverageStats['avg_o_3pa']) * 100, 1) : 0,
        isset($leagueAverageStats['avg_o_fta']) && $leagueAverageStats['avg_o_fta'] > 0 ? round(($leagueAverageStats['avg_o_ftm'] / $leagueAverageStats['avg_o_fta']) * 100, 1) : 0,
        round($leagueAverageStats['avg_o_reb'] ?? 0, 1),
        round($leagueAverageStats['avg_o_asts'] ?? 0, 1),
        round($leagueAverageStats['avg_o_stl'] ?? 0, 1),
        round($leagueAverageStats['avg_o_blk'] ?? 0, 1),
    ];

    $offensiveRadarData = [
        'labels' => $offensiveRadarLabels,
        'datasets' => [
            [
                'label' => htmlspecialchars($teamNameToDisplay),
                'data' => $teamOffensiveValues,
                'backgroundColor' => 'rgba(37, 99, 235, 0.4)', // Blue-600
                'borderColor' => 'rgba(37, 99, 235, 1)',
                'pointBackgroundColor' => 'rgba(37, 99, 235, 1)',
                'pointBorderColor' => '#fff',
                'pointHoverBackgroundColor' => '#fff',
                'pointHoverBorderColor' => 'rgba(37, 99, 235, 1)',
                'borderWidth' => 2
            ],
            [
                'label' => 'Rata-Rata Liga',
                'data' => $leagueAvgOffensiveValues,
                'backgroundColor' => 'rgba(209, 213, 219, 0.4)', // Gray-300
                'borderColor' => 'rgba(107, 114, 128, 1)', // Gray-500
                'pointBackgroundColor' => 'rgba(107, 114, 128, 1)',
                'pointBorderColor' => '#fff',
                'pointHoverBackgroundColor' => '#fff',
                'pointHoverBorderColor' => 'rgba(107, 114, 128, 1)',
                'borderWidth' => 2
            ]
        ]
    ];

    // Statistik defensif untuk radar (nilai lebih rendah lebih baik untuk poin, FG%, 3P% lawan)
    // (nilai lebih tinggi lebih baik untuk Forced TO, Steals, Blocks oleh tim kita)
    $defensiveRadarLabels = ['Opp Pts', 'Opp FG%', 'Opp 3P%', 'Opp Reb', 'Opp Asts', 'Forced TO', 'Steals (Def)', 'Blocks (Def)'];
    // Normalisasi: Untuk Opp Pts, Opp FG%, Opp 3P%, Opp Reb, Opp Asts, kita ingin nilai yang lebih rendah lebih baik.
    // Untuk radar, semua sumbu harus konsisten (misalnya, lebih tinggi = lebih baik).
    // Jadi, kita bisa membalik nilai-nilai ini (MAX_VALUE - value) atau menggunakan skala yang berbeda.
    // Untuk kesederhanaan, kita akan tampilkan apa adanya dan mengandalkan interpretasi pengguna,
    // atau kita bisa memilih statistik di mana "lebih tinggi lebih baik" untuk pertahanan, misal:
    // Forced Turnovers, Defensive Rebounds, Steals, Blocks.

    $teamDefensiveValues = [
        $teamData['d_pts'] ?? 0, // Lower is better
        isset($teamData['d_fga']) && $teamData['d_fga'] > 0 ? round(($teamData['d_fgm'] / $teamData['d_fga']) * 100, 1) : 100, // Lower is better
        isset($teamData['d_3pa']) && $teamData['d_3pa'] > 0 ? round(($teamData['d_3pm'] / $teamData['d_3pa']) * 100, 1) : 100, // Lower is better
        $teamData['d_reb'] ?? 0, // Lower is better (rebound lawan)
        $teamData['d_asts'] ?? 0, // Lower is better (assist lawan)
        $teamData['d_to'] ?? 0,   // Higher is better (turnover yang dipaksa dari lawan)
        $teamData['o_stl'] ?? 0,  // Steals tim kita adalah bagian dari pertahanan
        $teamData['o_blk'] ?? 0,  // Blocks tim kita adalah bagian dari pertahanan
    ];
    $leagueAvgDefensiveValues = [
        round($leagueAverageStats['avg_d_pts'] ?? 0, 1),
        isset($leagueAverageStats['avg_d_fga']) && $leagueAverageStats['avg_d_fga'] > 0 ? round(($leagueAverageStats['avg_d_fgm'] / $leagueAverageStats['avg_d_fga']) * 100, 1) : 100,
        isset($leagueAverageStats['avg_d_3pa']) && $leagueAverageStats['avg_d_3pa'] > 0 ? round(($leagueAverageStats['avg_d_3pm'] / $leagueAverageStats['avg_d_3pa']) * 100, 1) : 100,
        round($leagueAverageStats['avg_d_reb'] ?? 0, 1),
        round($leagueAverageStats['avg_d_asts'] ?? 0, 1),
        round($leagueAverageStats['avg_d_to'] ?? 0, 1),
        round($leagueAverageStats['avg_o_stl'] ?? 0, 1), // Rata-rata steals liga (digunakan sebagai proksi forced stl)
        round($leagueAverageStats['avg_o_blk'] ?? 0, 1), // Rata-rata blocks liga
    ];

    $defensiveRadarData = [
        'labels' => $defensiveRadarLabels,
        'datasets' => [
            [
                'label' => htmlspecialchars($teamNameToDisplay),
                'data' => $teamDefensiveValues,
                'backgroundColor' => 'rgba(220, 38, 38, 0.4)', // Red-600
                'borderColor' => 'rgba(220, 38, 38, 1)',
                'pointBackgroundColor' => 'rgba(220, 38, 38, 1)',
                'pointBorderColor' => '#fff',
                'pointHoverBackgroundColor' => '#fff',
                'pointHoverBorderColor' => 'rgba(220, 38, 38, 1)',
                'borderWidth' => 2
            ],
            [
                'label' => 'Rata-Rata Liga',
                'data' => $leagueAvgDefensiveValues,
                'backgroundColor' => 'rgba(209, 213, 219, 0.4)',
                'borderColor' => 'rgba(107, 114, 128, 1)',
                'pointBackgroundColor' => 'rgba(107, 114, 128, 1)',
                'pointBorderColor' => '#fff',
                'pointHoverBackgroundColor' => '#fff',
                'pointHoverBorderColor' => 'rgba(107, 114, 128, 1)',
                'borderWidth' => 2
            ]
        ]
    ];
}
// ----------------------------------------------

// Data untuk Chart Perbandingan (Bar Chart) - sudah ada
$comparisonChartData = null;
// ... (kode $comparisonChartData Anda yang sudah ada tetap di sini) ...
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
        .chart-wrapper { background-color: #F9FAFB; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.06); min-height: 400px; /* Ensure space for radar */ }
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
                    <!-- Data ringkasan umum tetap di sini -->
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

            <!-- ---- SEKSI BARU UNTUK RADAR CHARTS ---- -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <?php if ($offensiveRadarData): ?>
                <section>
                    <h2 class="section-title">Statistik Ofensif (Radar)</h2>
                    <div class="chart-wrapper mt-4">
                        <canvas id="offensiveRadarChart"></canvas>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($defensiveRadarData): ?>
                <section>
                    <h2 class="section-title">Statistik Defensif (Radar)</h2>
                    <div class="chart-wrapper mt-4">
                        <canvas id="defensiveRadarChart"></canvas>
                    </div>
                </section>
                <?php endif; ?>
            </div>
            <!-- --------------------------------------- -->


            <?php if ($comparisonChartData): ?>
            <section class="mb-8">
                <h2 class="section-title">Perbandingan Umum dengan Rata-Rata Liga (Musim <?= $year_param ?>)</h2>
                <div class="chart-wrapper mt-4 min-h-[450px] lg:min-h-[500px]">
                    <canvas id="teamComparisonChart"></canvas>
                </div>
            </section>
            <?php else: ?>
                <p class="text-center text-slate-500 my-8 bg-slate-50 p-4 rounded-md">Data rata-rata liga tidak tersedia untuk perbandingan umum pada musim ini.</p>
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
        const offensiveRadarDataJS = <?= json_encode($offensiveRadarData ?? null) ?>;
        const defensiveRadarDataJS = <?= json_encode($defensiveRadarData ?? null) ?>;

        // Fungsi untuk membuat radar chart
        function createRadarChart(canvasId, chartData, titleText) {
            const ctx = document.getElementById(canvasId)?.getContext('2d');
            if (ctx && chartData) {
                new Chart(ctx, {
                    type: 'radar',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top', labels: { font: { size: 11 }, padding: 10, usePointStyle: true, boxWidth: 8 } },
                            title: { display: false, text: titleText, font: { size: 16, weight: 'bold' }, padding: { top: 5, bottom: 15 } },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) { label += ': '; }
                                        label += context.formattedValue;
                                        // Tambahkan catatan untuk statistik defensif jika perlu
                                        if (canvasId === 'defensiveRadarChart' && (context.label.startsWith('Opp'))) {
                                            label += ' (Lower is Better)';
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            r: { // Skala radial
                                beginAtZero: true, // Atau false jika Anda ingin skala otomatis yang mungkin tidak mulai dari 0
                                // Anda mungkin perlu menyesuaikan min/max atau suggestedMin/suggestedMax
                                // tergantung pada rentang nilai statistik Anda untuk tampilan yang lebih baik.
                                // Misalnya:
                                // suggestedMin: 0,
                                // suggestedMax: 100, // Jika sebagian besar data persentase
                                pointLabels: { font: { size: 10 } },
                                grid: { color: '#e2e8f0' },
                                angleLines: { color: '#e2e8f0' }
                            }
                        },
                        elements: {
                            line: { borderWidth: 2 },
                            point: { radius: 3, hoverRadius: 5 }
                        }
                    }
                });
            } else if (document.getElementById(canvasId)) {
                 document.getElementById(canvasId).parentNode.innerHTML = `<p class="text-center text-sm text-gray-500 py-8">Data tidak cukup untuk menampilkan ${titleText.toLowerCase()}.</p>`;
            }
        }

        // Inisialisasi Radar Charts
        createRadarChart('offensiveRadarChart', offensiveRadarDataJS, 'Statistik Ofensif Tim vs Liga');
        createRadarChart('defensiveRadarChart', defensiveRadarDataJS, 'Statistik Defensif Tim vs Liga');


        // Bar Chart Perbandingan Umum (kode yang sudah ada)
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
                            backgroundColor: '#fff', titleColor: '#1e293b', bodyColor: '#475563',
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
            document.getElementById('teamComparisonChart').parentNode.innerHTML = '<p class="text-center text-sm text-gray-500 py-8">Tidak ada data yang cukup untuk menampilkan chart perbandingan umum.</p>';
        }
    </script>
</body>
</html>