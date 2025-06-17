<?php
require_once 'db.php';

// Menerima parameter
$year_param = $_GET['year'] ?? null;
$tmID_param = $_GET['tmID'] ?? null;

if (!$year_param || !$tmID_param) {
    die("Parameter tahun (year) dan ID Tim (tmID) diperlukan.");
}

// Persiapan variabel tahun
$display_season_year = (int)$year_param;
$season_start_year_for_db = $display_season_year - 1;

// Definisi mapping yang akan digunakan di beberapa tempat
$roundOrderMap = ['CFR' => 1, 'CSF' => 2, 'CF' => 3, 'F' => 4];
$rankToFullNameMap = [
    1 => 'First Round',
    2 => 'Conference Semifinals',
    3 => 'Conference Finals',
    4 => 'Finals',
];
$abbrevToFullNameMap = [
    'CFR' => 'First Round',
    'CSF' => 'Conference Semifinals',
    'CF' => 'Conference Finals',
    'F' => 'Finals',
];

// --- [QUERY BLOK 1: DATA UTAMA MUSIM INI] ---
$pipelinePlayoff = [
    ['$unwind' => '$teams'],
    ['$unwind' => '$teams.team_season_details'],
    [
        '$match' => [
            'teams.team_season_details.year' => $season_start_year_for_db,
            'teams.team_season_details.tmID' => $tmID_param
        ]
    ],
    ['$limit' => 1],
    [
        '$project' => [
            '_id' => 0,
            'team_details' => '$teams.team_season_details',
            'playoff_series' => '$teams.playoff_series_for_team'
        ]
    ]
];

$result = $coaches_collection->aggregate($pipelinePlayoff)->toArray();
$teamDetail = null;
$playoffDataForDisplay = [];
$teamNameToDisplay = $tmID_param;

if (!empty($result)) {
    $teamDetail = $result[0]['team_details'] ?? null;
    $bsonPlayoffData = $result[0]['playoff_series'] ?? null;
    if ($bsonPlayoffData) {
        $playoffDataForDisplay = iterator_to_array($bsonPlayoffData);
    }
    if ($teamDetail) {
        $teamNameToDisplay = $teamDetail['name'] ?? $tmID_param;
    }
}

if (!$teamDetail) {
    die("Detail tim tidak ditemukan untuk ".htmlspecialchars($tmID_param)." pada musim ".htmlspecialchars($display_season_year).".");
}

// --- [QUERY BLOK 2: "TAMBALAN" HASIL PLAYOFF] ---
$correctPlayoffResult = '-'; // Nilai default
try {
    $year_for_correct_playoff = $season_start_year_for_db + 1;

    $pipelineCorrectPlayoff = [
        ['$unwind' => '$teams'],
        ['$unwind' => '$teams.team_season_details'],
        [
            '$match' => [
                'teams.team_season_details.year' => $year_for_correct_playoff,
                'teams.team_season_details.tmID' => $tmID_param
            ]
        ],
        ['$limit' => 1],
        [
            '$project' => [
                '_id' => 0,
                'correct_playoff_value' => '$teams.team_season_details.playoff'
            ]
        ]
    ];
    $correctResultCursor = $coaches_collection->aggregate($pipelineCorrectPlayoff)->toArray();

    if (!empty($correctResultCursor)) {
        $correctPlayoffResult = $correctResultCursor[0]['correct_playoff_value'] ?? '-';
    }

} catch (Exception $e) {
    $correctPlayoffResult = '-';
}


// --- [QUERY BLOK 3: DATA HISTORIS UNTUK GRAFIK] ---
$historicalPlayoffData = [];
try {
    $pipelineHistorical = [
        ['$unwind' => '$teams'],
        ['$unwind' => '$teams.playoff_series_for_team'],
        [
            '$match' => [
                '$or' => [
                    ['teams.playoff_series_for_team.tmIDWinner' => $tmID_param],
                    ['teams.playoff_series_for_team.tmIDLoser' => $tmID_param]
                ]
            ]
        ],
        [
            '$project' => [
                '_id' => 0,
                'year' => '$teams.playoff_series_for_team.year',
                'round' => '$teams.playoff_series_for_team.round'
            ]
        ],
        ['$sort' => ['year' => 1]]
    ];

    $all_series_cursor = $coaches_collection->aggregate($pipelineHistorical);
    $all_series = $all_series_cursor->toArray();

    $yearly_progress = [];
    foreach ($all_series as $series) {
        $start_year = $series['year'];
        $display_year = $start_year;
        $round_rank = $roundOrderMap[$series['round']] ?? 0;

        if (!isset($yearly_progress[$display_year]) || $round_rank > $yearly_progress[$display_year]) {
            $yearly_progress[$display_year] = $round_rank;
        }
    }
    
    if (count($yearly_progress) > 20) {
         $yearly_progress = array_slice($yearly_progress, -20, 20, true);
    }

    if (!empty($yearly_progress)) {
        $historicalPlayoffData['years'] = array_keys($yearly_progress);
        $historicalPlayoffData['rounds_rank'] = array_values($yearly_progress);
    }

} catch (Exception $e) {
    $historicalPlayoffData = [];
    // error_log("MongoDB historical query error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Playoff Tim: <?= htmlspecialchars($teamNameToDisplay) ?> (Musim <?= $display_season_year ?>)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Roboto+Condensed:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Montserrat', sans-serif; background-color: #0A0A14; color: #d1d5db; padding-bottom: 2rem; }
        .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
        .main-container { max-width: 1350px; margin: 2rem auto; background-color: rgba(17, 24, 39, 0.8); padding: 2rem; border-radius: 1rem; border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 10px 30px rgba(0,0,0,0.3); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
        .stat-card-playoff { background-color: rgba(31, 41, 55, 0.5); border-radius: 0.75rem; padding: 1rem; text-align: center; border: 1px solid rgba(255, 255, 255, 0.1); transition: all 0.3s ease; }
        .stat-card-playoff:hover { background-color: rgba(55, 65, 81, 0.7); transform: translateY(-4px); }
        .stat-label-playoff { color: #9ca3af; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.5rem; }
        .stat-value-playoff { color: #ffffff; font-size: 1.75rem; font-weight: 700; font-family: 'Roboto Condensed', sans-serif; }
        .back-link { display: inline-block; margin-top: 2rem; padding: 0.625rem 1.25rem; background-color: #4f46e5; color: white; border-radius: 0.5rem; font-weight: 600; text-decoration: none; text-align: center; transition: background-color 0.3s ease; }
        .back-link:hover { background-color: #6366f1; }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="text-center">
            <a href="teams_stats.php<?= isset($_GET['team_season']) ? '?team_season=' . htmlspecialchars($_GET['team_season']) : '' ?>" class="text-sm text-indigo-400 hover:text-indigo-300 hover:underline mb-4 inline-block">
               ← Kembali ke Performa Tim
            </a>
            <h1 class="font-condensed text-4xl font-bold uppercase tracking-wider text-white mb-2"><?= htmlspecialchars($teamNameToDisplay) ?></h1>
            <p class="text-xl text-indigo-400 mb-6">Detail Playoff Musim <?= $display_season_year ?></p>
        </div>

        <?php if ($teamDetail): ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
            <div class="stat-card-playoff"><span class="stat-label-playoff">Musim Reguler W</span><span class="stat-value-playoff"><?= htmlspecialchars($teamDetail['won'] ?? '-') ?></span></div>
            <div class="stat-card-playoff"><span class="stat-label-playoff">Musim Reguler L</span><span class="stat-value-playoff"><?= htmlspecialchars($teamDetail['lost'] ?? '-') ?></span></div>
            <div class="stat-card-playoff"><span class="stat-label-playoff">Rank</span><span class="stat-value-playoff"><?= htmlspecialchars($teamDetail['rank'] ?? '-') ?> <span class="text-base text-slate-300"><?= htmlspecialchars($teamDetail['confID'] ?? '') ?></span></span></div>
            <div class="stat-card-playoff">
                <span class="stat-label-playoff">Hasil Playoff</span>
                <span class="stat-value-playoff text-lg"><?= htmlspecialchars($correctPlayoffResult) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($playoffDataForDisplay)): ?>
            <section class="mb-8">
                <h2 class="font-condensed text-2xl font-bold uppercase tracking-wider text-indigo-400 mb-4 pb-2 border-b-2 border-indigo-500/30">Rincian Seri Playoff (Musim <?= $display_season_year ?>)</h2>
                <div class="overflow-x-auto bg-gray-900/50 rounded-lg border border-slate-700">
                    <table class="min-w-full table-auto text-sm">
                        <thead class="bg-gray-900/70">
                            <tr class="text-left text-xs text-indigo-400 uppercase tracking-wider">
                                <th class="px-4 py-3 font-semibold">Round</th>
                                <th class="px-4 py-3 font-semibold">Lawan</th>
                                <th class="px-4 py-3 font-semibold">Hasil (Tim Ini)</th>
                                <th class="px-4 py-3 font-semibold">Skor Seri (W-L)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700">
                            <?php
                                usort($playoffDataForDisplay, function($a, $b) use ($roundOrderMap) {
                                   return ($roundOrderMap[$a['round']] ?? 99) <=> ($roundOrderMap[$b['round']] ?? 99);
                                });
                            ?>
                            <?php foreach ($playoffDataForDisplay as $series): ?>
                                <?php
                                $isThisTeamWinner = ($series['tmIDWinner'] ?? null) === $tmID_param;
                                $opponentTeamID = $isThisTeamWinner ? ($series['tmIDLoser'] ?? 'N/A') : ($series['tmIDWinner'] ?? 'N/A');
                                // [PERUBAHAN] Menggunakan map untuk menampilkan nama ronde yang lebih deskriptif
                                $roundFullName = $abbrevToFullNameMap[$series['round']] ?? $series['round'];
                                $resultText = $isThisTeamWinner ? "Menang" : "Kalah";
                                $seriesScore = $isThisTeamWinner ? (($series['W'] ?? 0) . " - " . ($series['L'] ?? 0)) : (($series['L'] ?? 0) . " - " . ($series['W'] ?? 0));
                                ?>
                                <tr class="hover:bg-slate-700/50">
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold text-white"><?= htmlspecialchars($roundFullName) ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-300"><?= htmlspecialchars($opponentTeamID) ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap font-bold <?= $isThisTeamWinner ? 'text-green-400' : 'text-red-400' ?>"><?= $resultText ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono text-white"><?= htmlspecialchars($seriesScore) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php else: ?>
            <div class="text-center py-10 bg-gray-900/50 rounded-lg border border-slate-700 mt-8">
                <i class="fas fa-info-circle fa-3x text-slate-500 mb-4"></i>
                <p class="text-slate-400 text-lg">
                    <?php
                    if ($teamDetail && ($teamDetail['playoff'] === 'Y' || stripos($teamDetail['playoff'], 'Won') !== false || stripos($teamDetail['playoff'], 'Lost') !== false )) {
                        echo 'Tidak ada detail pertandingan seri playoff yang ditemukan untuk tim ini pada musim ' . $display_season_year . '.';
                    } else {
                        echo 'Tim tidak lolos playoff pada musim ' . $display_season_year . '.';
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- [PERUBAHAN] Section untuk Chart Historis Baru -->
        <?php if (!empty($historicalPlayoffData['years'])): ?>
        <section class="bg-gray-900/50 p-4 sm:p-6 rounded-lg border border-slate-700 min-h-[400px] mt-12">
            <h3 class="font-condensed text-2xl font-bold uppercase tracking-wider text-indigo-400 mb-4 text-center">
                Jejak Playoff Tim (Beberapa Musim Terakhir)
            </h3>
            <div class="relative h-[350px] md:h-[400px]">
                <canvas id="historicalPlayoffChart"></canvas>
            </div>
        </section>
        <?php endif; ?>

        <div class="text-center mt-8">
            <a href="teams_stats.php<?= isset($_GET['team_season']) ? '?team_season=' . htmlspecialchars($_GET['team_season']) : '' ?>" class="back-link">
                Kembali
            </a>
        </div>
    </div>

    <!-- [PERUBAHAN] Script untuk Chart Historis Baru -->
    <?php if (!empty($historicalPlayoffData['years'])): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('historicalPlayoffChart')?.getContext('2d');
        if (ctx) {
            const historicalYears = <?= json_encode($historicalPlayoffData['years']) ?>;
            const historicalRanks = <?= json_encode($historicalPlayoffData['rounds_rank']) ?>;
            const rankToNameMap = <?= json_encode($rankToFullNameMap) ?>;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: historicalYears, // Sumbu X: Tahun
                    datasets: [{
                        label: 'Babak Tercapai',
                        data: historicalRanks, // Data: Peringkat babak (1-4)
                        fill: {
                            target: 'origin',
                            above: 'rgba(99, 102, 241, 0.2)', // Area di bawah garis
                            below: 'rgba(99, 102, 241, 0.2)'
                        },
                        borderColor: 'rgba(129, 140, 248, 1)', // Indigo-400
                        backgroundColor: 'rgba(129, 140, 248, 1)',
                        tension: 0.1,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: 'rgba(129, 140, 248, 1)',
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        pointHoverBackgroundColor: '#fff',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(17, 24, 39, 0.9)',
                            titleColor: '#c7d2fe',
                            bodyColor: '#e5e7eb',
                            borderColor: 'rgba(79, 70, 229, 0.8)',
                            borderWidth: 1,
                            padding: 10,
                            callbacks: {
                                title: function(context) { return 'Musim ' + context[0].label; },
                                label: function(context) {
                                    const rank = context.parsed.y;
                                    const roundName = rankToNameMap[rank] || 'Tidak Lolos';
                                    return `Pencapaian: ${roundName}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: 'rgba(156, 163, 175, 0.8)' },
                            grid: { color: 'rgba(55, 65, 81, 0.3)' }
                        },
                        y: {
                            min: 0,
                            max: 4.5, // Beri sedikit ruang di atas
                            ticks: {
                                color: 'rgba(156, 163, 175, 0.9)',
                                stepSize: 1,
                                padding: 10,
                                callback: function(value, index, ticks) {
                                    // Tampilkan label nama babak, dan 'Tidak Lolos' untuk nilai 0
                                    if (value === 0) return 'Tidak Lolos';
                                    return rankToNameMap[value] || '';
                                }
                            },
                            grid: { color: 'rgba(55, 65, 81, 0.5)' }
                        }
                    }
                }
            });
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>