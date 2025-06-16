<?php
require_once 'db.php';
// include 'header.php';

// Menerima parameter TAHUN AKHIR MUSIM (misal: 2010 untuk musim 2009-2010)
$year_param = $_GET['year'] ?? null;
$tmID_param = $_GET['tmID'] ?? null;

if (!$year_param || !$tmID_param) {
    die("Parameter tahun (year) dan ID Tim (tmID) diperlukan.");
}

// Variabel untuk ditampilkan di halaman adalah tahun yang dipilih pengguna (TAHUN AKHIR MUSIM).
$display_season_year = (int)$year_param;

// Database menyimpan data berdasarkan TAHUN AWAL MUSIM.
// Jadi, kita kurangi 1 untuk melakukan query ke database.
$season_start_year_for_db = $display_season_year - 1;

// Ambil data tim dan data playoff dari database
$pipelinePlayoff = [
    ['$unwind' => '$teams'],
    ['$unwind' => '$teams.team_season_details'],
    [
        '$match' => [
            // Query ke database menggunakan TAHUN AWAL MUSIM
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
$teamAndPlayoffData = null;
$teamDetail = null;
$playoffDataForDisplay = [];
$teamNameToDisplay = $tmID_param;

if (!empty($result)) {
    $teamAndPlayoffData = $result[0];
    $teamDetail = $teamAndPlayoffData['team_details'] ?? null;
    
    // Ambil data dari hasil query (ini mungkin BSONArray)
    $bsonPlayoffData = $teamAndPlayoffData['playoff_series'] ?? null;

    // Konversi BSONArray menjadi PHP array asli jika ada isinya
    if ($bsonPlayoffData) {
        $playoffDataForDisplay = iterator_to_array($bsonPlayoffData);
    } else {
        $playoffDataForDisplay = [];
    }

    if ($teamDetail) {
        $teamNameToDisplay = $teamDetail['name'] ?? $tmID_param;
    }
}

if (!$teamDetail) {
    // Pesan error sekarang menggunakan tahun tampilan yang benar
    die("Detail tim tidak ditemukan untuk ".htmlspecialchars($tmID_param)." pada musim ".htmlspecialchars($display_season_year).".");
}

// Siapkan data untuk chart playoff (jika ada)
$playoffRoundsChart = [];
$finalPlayoffLabels = [];
$finalPlayoffWins = [];
if (!empty($playoffDataForDisplay)) {
    // Map untuk urutan ronde playoff yang benar
    $roundOrderMap = ['CFR' => 1, 'CSF' => 2, 'CF' => 3, 'F' => 4];

    foreach ($playoffDataForDisplay as $playoff_series) {
        $isWinner = ($playoff_series['tmIDWinner'] ?? null) === $tmID_param;
        $roundLabel = ($playoff_series['round'] ?? 'N/A');

        // Hanya tambahkan jika round belum ada untuk menghindari duplikasi
        if (!in_array($roundLabel, array_column($playoffRoundsChart, 'label'))) {
            $winsThisSeries = 0;
            if ($isWinner) {
                $winsThisSeries = (int)($playoff_series['W'] ?? 0);
            } else {
                $winsThisSeries = (int)($playoff_series['L'] ?? 0);
            }
            $playoffRoundsChart[] = ['label' => $roundLabel, 'wins' => $winsThisSeries];
        }
    }

    // Urutkan ronde playoff berdasarkan map yang sudah ditentukan
    usort($playoffRoundsChart, function($a, $b) use ($roundOrderMap) {
        return ($roundOrderMap[$a['label']] ?? 99) <=> ($roundOrderMap[$b['label']] ?? 99);
    });

    // Pisahkan lagi data label dan kemenangan setelah diurutkan
    $finalPlayoffLabels = array_column($playoffRoundsChart, 'label');
    $finalPlayoffWins = array_column($playoffRoundsChart, 'wins');
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
        body { 
            font-family: 'Montserrat', sans-serif; 
            background-color: #0A0A14; 
            color: #d1d5db; /* Warna teks default: light gray */
            padding-bottom: 2rem;
        }
        .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
        
        /* Container utama dengan efek glassmorphism */
        .main-container {
            max-width: 1350px; 
            margin: 2rem auto; 
            background-color: rgba(17, 24, 39, 0.8); /* Dark blue/gray semi-transparan */
            padding: 2rem; 
            border-radius: 1rem; 
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        
        /* Kartu Statistik dengan tema gelap */
        .stat-card-playoff { 
            background-color: rgba(31, 41, 55, 0.5); /* Gray-800 semi-transparan */
            border-radius: 0.75rem; 
            padding: 1rem; 
            text-align: center; 
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        .stat-card-playoff:hover {
            background-color: rgba(55, 65, 81, 0.7);
            transform: translateY(-4px);
        }
        .stat-label-playoff { 
            color: #9ca3af; /* Gray-400 */
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block; 
            margin-bottom: 0.5rem;
        }
        .stat-value-playoff { 
            color: #ffffff; 
            font-size: 1.75rem; 
            font-weight: 700; 
            font-family: 'Roboto Condensed', sans-serif;
        }

        /* Tombol kembali */
        .back-link { 
            display: inline-block; 
            margin-top: 2rem; 
            padding: 0.625rem 1.25rem; 
            background-color: #4f46e5; /* Indigo-600 */
            color: white; 
            border-radius: 0.5rem; 
            font-weight: 600; 
            text-decoration: none; 
            text-align: center; 
            transition: background-color 0.3s ease; 
        }
        .back-link:hover { background-color: #6366f1; /* Indigo-500 */ }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="text-center">
            <a href="teams_stats.php<?= isset($_GET['team_season']) ? '?team_season=' . htmlspecialchars($_GET['team_season']) : '' ?>"
               class="text-sm text-indigo-400 hover:text-indigo-300 hover:underline mb-4 inline-block">
               ← Kembali ke Performa Tim
            </a>
            <h1 class="font-condensed text-4xl font-bold uppercase tracking-wider text-white mb-2"><?= htmlspecialchars($teamNameToDisplay) ?></h1>
            <p class="text-xl text-indigo-400 mb-6">Detail Playoff Musim <?= $display_season_year ?></p>
        </div>

        <?php if ($teamDetail): ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
            <div class="stat-card-playoff">
                <span class="stat-label-playoff">Musim Reguler W</span>
                <span class="stat-value-playoff"><?= htmlspecialchars($teamDetail['won'] ?? '-') ?></span>
            </div>
            <div class="stat-card-playoff">
                <span class="stat-label-playoff">Musim Reguler L</span>
                <span class="stat-value-playoff"><?= htmlspecialchars($teamDetail['lost'] ?? '-') ?></span>
            </div>
            <div class="stat-card-playoff">
                <span class="stat-label-playoff">Rank</span>
                <span class="stat-value-playoff"><?= htmlspecialchars($teamDetail['rank'] ?? '-') ?> <span class="text-base text-slate-300"><?= htmlspecialchars($teamDetail['confID'] ?? '') ?></span></span>
            </div>
            <div class="stat-card-playoff">
                <span class="stat-label-playoff">Hasil Playoff</span>
                <span class="stat-value-playoff text-lg"><?= htmlspecialchars($teamDetail['playoff'] ?? '-') ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($playoffDataForDisplay)): ?>
            <section class="mb-8">
                <h2 class="font-condensed text-2xl font-bold uppercase tracking-wider text-indigo-400 mb-4 pb-2 border-b-2 border-indigo-500/30">Rincian Seri Playoff</h2>
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
                                $opponentName = $opponentTeamID;
                                $resultText = $isThisTeamWinner ? "Menang" : "Kalah";
                                $seriesScore = $isThisTeamWinner ? (($series['W'] ?? 0) . " - " . ($series['L'] ?? 0)) : (($series['L'] ?? 0) . " - " . ($series['W'] ?? 0));
                                ?>
                                <tr class="hover:bg-slate-700/50">
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold text-white"><?= htmlspecialchars($series['round'] ?? '-') ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-300"><?= htmlspecialchars($opponentName) ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap font-bold <?= $isThisTeamWinner ? 'text-green-400' : 'text-red-400' ?>">
                                        <?= $resultText ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap font-mono text-white"><?= htmlspecialchars($seriesScore) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php if (!empty($finalPlayoffLabels)): ?>
            <section class="bg-gray-900/50 p-4 rounded-lg border border-slate-700 min-h-[300px] md:min-h-[350px] mt-8">
                <h3 class="text-lg font-semibold mb-4 text-slate-300 text-center">Grafik Kemenangan Tim per Babak Playoff</h3>
                <canvas id="playoffRoundWinsChart"></canvas>
            </section>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-10 bg-gray-900/50 rounded-lg border border-slate-700">
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

        <div class="text-center mt-8">
            <a href="teams_stats.php<?= isset($_GET['team_season']) ? '?team_season=' . htmlspecialchars($_GET['team_season']) : '' ?>" class="back-link">
                Kembali
            </a>
        </div>
    </div>

    <?php if (!empty($finalPlayoffLabels)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctxPlayoff = document.getElementById('playoffRoundWinsChart')?.getContext('2d');
            if (ctxPlayoff) {
                new Chart(ctxPlayoff, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($finalPlayoffLabels) ?>,
                        datasets: [{
                            label: 'Kemenangan Tim Ini di Babak',
                            data: <?= json_encode($finalPlayoffWins) ?>,
                            backgroundColor: 'rgba(99, 102, 241, 0.6)',  /* Indigo-500 semi-transparan */
                            borderColor: 'rgba(129, 140, 248, 1)',   /* Indigo-400 */
                            borderWidth: 2,
                            borderRadius: {topLeft: 4, topRight: 4},
                            hoverBackgroundColor: 'rgba(129, 140, 248, 0.8)'
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        indexAxis: <?= count($finalPlayoffLabels) > 4 ? "'y'" : "'x'" ?>,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(17, 24, 39, 0.9)',
                                titleColor: '#c7d2fe', /* Indigo-200 */
                                bodyColor: '#e5e7eb',  /* Gray-200 */
                                borderColor: 'rgba(79, 70, 229, 0.8)',
                                borderWidth: 1,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) { label += ': '; }
                                        const value = context.parsed.y !== null ? context.parsed.y : context.parsed.x;
                                        label += value + ' kemenangan';
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: { 
                                beginAtZero: true, 
                                ticks: { 
                                    stepSize: 1, 
                                    precision: 0,
                                    color: 'rgba(156, 163, 175, 0.8)' /* Gray-400 */
                                },
                                grid: {
                                    color: 'rgba(55, 65, 81, 0.5)' /* Gray-700 */
                                }
                            },
                            x: { 
                                ticks: { 
                                    color: 'rgba(156, 163, 175, 0.8)' /* Gray-400 */
                                },
                                grid: {
                                    color: 'rgba(55, 65, 81, 0.5)' /* Gray-700 */
                                }
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