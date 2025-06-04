<?php
require_once 'db.php';
// include 'header.php';

$year_param = $_GET['year'] ?? null;
$tmID_param = $_GET['tmID'] ?? null; // Gunakan tmID sebagai identifier utama, bukan nama
// $teamName_param = $_GET['name'] ?? null; // Nama bisa diambil nanti

if (!$year_param || !$tmID_param) {
    die("Parameter tahun (year) dan ID Tim (tmID) diperlukan.");
}
$year_param_int = (int)$year_param;

// Ambil data tim dan data playoff yang disematkan dari coaches_collection
$pipelinePlayoff = [
    ['$unwind' => '$teams'],
    ['$unwind' => '$teams.team_season_details'], // Kita butuh ini untuk info tim
    [
        '$match' => [
            'teams.team_season_details.year' => $year_param_int,
            'teams.team_season_details.tmID' => $tmID_param
        ]
    ],
    ['$limit' => 1], 
    // Ambil field yang dibutuhkan: detail tim dan seri playoff untuk tim tersebut pada tahun itu
    [
        '$project' => [
            '_id' => 0,
            'team_details' => '$teams.team_season_details',
            'playoff_series' => '$teams.playoff_series_for_team' // Ini array seri playoff yang disematkan
        ]
    ]
];

$result = $coaches_collection->aggregate($pipelinePlayoff)->toArray();
$teamAndPlayoffData = null;
$teamDetail = null;
$playoffDataForDisplay = []; // Ini akan berisi seri playoff
$teamNameToDisplay = $tmID_param; // Default

if (!empty($result)) {
    $teamAndPlayoffData = $result[0];
    $teamDetail = $teamAndPlayoffData['team_details'] ?? null;
    $playoffDataForDisplay = $teamAndPlayoffData['playoff_series'] ?? [];
    if ($teamDetail) {
        $teamNameToDisplay = $teamDetail['name'] ?? $tmID_param;
    }
}

if (!$teamDetail) {
    die("Detail tim tidak ditemukan untuk ".htmlspecialchars($tmID_param)." musim ".htmlspecialchars($year_param).".");
}

function calculateWinPercentage($won, $lost, $games = null) { /* ... sama ... */ }

// Siapkan data untuk chart playoff (jika ada)
$playoffRoundsChart = [];
$playoffWinsInRoundChart = [];
if (!empty($playoffDataForDisplay)) {
    foreach ($playoffDataForDisplay as $playoff_series) {
        // Asumsi $playoff_series adalah objek tunggal dari array playoff_series_for_team
        // Kita perlu menentukan apakah tim ini menang atau kalah dalam seri tersebut
        $isWinner = ($playoff_series['tmIDWinner'] ?? null) === $tmID_param;
        $roundLabel = "Round " . ($playoff_series['round'] ?? 'N/A');
        
        // Untuk chart, kita bisa fokus pada kemenangan tim ini per round
        // Atau bisa juga W-L
        if (!in_array($roundLabel, $playoffRoundsChart)) { // Hindari duplikasi round jika ada beberapa game dalam seri
            $playoffRoundsChart[] = $roundLabel;
            // Hitung kemenangan tim ini di seri tersebut
            $winsThisSeries = 0;
            if ($isWinner) {
                $winsThisSeries = (int)($playoff_series['W'] ?? 0);
            } else {
                // Jika kalah, lawannya menang sebanyak $playoff_series['W'], tim ini menang $playoff_series['L']
                $winsThisSeries = (int)($playoff_series['L'] ?? 0); 
            }
            $playoffWinsInRoundChart[] = $winsThisSeries;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Playoff Tim: <?= htmlspecialchars($teamNameToDisplay) ?> (<?= $year_param ?>)</title>
    <!-- (Salin semua link CSS dan JS dari kode team_playoff.php lama Anda) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Roboto+Condensed:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* (Salin style dari team_playoff.php lama Anda, sesuaikan jika perlu) */
        body { font-family: 'Montserrat', sans-serif; background-color: #f0f9ff; /* Light blue */ color: #1e293b; padding-bottom: 2rem;}
        .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
        .container { max-width: 900px; margin: 2rem auto; background-color: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); }
        .header-title { color: #1e3a8a; /* Indigo-800 */ font-size: 2.25rem; margin-bottom: 0.5rem;}
        .header-subtitle { color: #475569; /* Slate-600 */ font-size: 1.25rem; margin-bottom: 1.5rem; }
        .stat-card-playoff { background-color: #e0f2fe; /* Light cyan */ border-radius: 0.75rem; padding: 1rem; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .stat-label-playoff { color: #075985; /* Cyan-700 */ font-size: 0.875rem; }
        .stat-value-playoff { color: #0c4a6e; /* Cyan-800 */ font-size: 1.5rem; font-weight: 700; }
        .section-heading { font-family: 'Roboto Condensed', sans-serif; font-size: 1.5rem; font-weight: 700; color: #1e40af; /* Indigo-700 */ margin-bottom: 1rem; border-bottom: 2px solid #93c5fd; padding-bottom: 0.5rem;}
        .back-link { display: inline-block; margin-top: 2rem; padding: 0.625rem 1.25rem; background-color: #4f46e5; color: white; border-radius: 0.5rem; font-weight: 600; text-decoration: none; text-align: center; transition: background-color 0.3s ease; }
        .back-link:hover { background-color: #4338ca; }
    </style>
</head>
<body>
    <div class="container">
        <div class="text-center">
            <a href="teams_stats.php<?= isset($_GET['team_season']) ? '?team_season=' . htmlspecialchars($_GET['team_season']) : '' ?>" 
               class="text-sm text-indigo-600 hover:text-indigo-700 hover:underline mb-4 inline-block">
               ← Kembali ke Performa Tim
            </a>
            <h1 class="header-title font-condensed"><?= htmlspecialchars($teamNameToDisplay) ?></h1>
            <p class="header-subtitle">Detail Playoff Musim <?= htmlspecialchars($year_param) ?></p>
        </div>

        <?php if ($teamDetail): ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="stat-card-playoff"><span class="stat-label-playoff">Musim Reguler W</span><span class="stat-value-playoff"><?= htmlspecialchars($teamDetail['won'] ?? '-') ?></span></div>
            <div class="stat-card-playoff"><span class="stat-label-playoff">Musim Reguler L</span><span class="stat-value-playoff"><?= htmlspecialchars($teamDetail['lost'] ?? '-') ?></span></div>
            <div class="stat-card-playoff"><span class="stat-label-playoff">Rank</span><span class="stat-value-playoff"><?= htmlspecialchars($teamDetail['rank'] ?? '-') ?> <?= htmlspecialchars($teamDetail['confID'] ?? '') ?></span></div>
            <div class="stat-card-playoff"><span class="stat-label-playoff">Hasil Playoff (Reg)</span><span class="stat-value-playoff"><?= htmlspecialchars($teamDetail['playoff'] ?? '-') ?></span></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($playoffDataForDisplay)): ?>
            <section class="mb-8">
                <h2 class="section-heading">Rincian Seri Playoff</h2>
                <div class="overflow-x-auto bg-white rounded-md shadow">
                    <table class="min-w-full table-auto text-sm">
                        <thead class="bg-slate-100">
                            <tr class="text-left text-xs text-slate-600 uppercase tracking-wider">
                                <th class="px-4 py-2">Round</th>
                                <th class="px-4 py-2">Lawan</th>
                                <th class="px-4 py-2">Hasil (Tim Ini)</th>
                                <th class="px-4 py-2">Skor Seri (W-L)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php foreach ($playoffDataForDisplay as $series): ?>
                                <?php
                                $isThisTeamWinner = ($series['tmIDWinner'] ?? null) === $tmID_param;
                                $opponentTeamID = $isThisTeamWinner ? ($series['tmIDLoser'] ?? 'N/A') : ($series['tmIDWinner'] ?? 'N/A');
                                // Anda mungkin perlu lookup nama tim lawan jika tmID tidak cukup
                                $opponentName = $opponentTeamID; // Placeholder, idealnya lookup nama
                                
                                $resultText = $isThisTeamWinner ? "Menang" : "Kalah";
                                $seriesScore = $isThisTeamWinner ? (($series['W'] ?? 0) . " - " . ($series['L'] ?? 0)) : (($series['L'] ?? 0) . " - " . ($series['W'] ?? 0));
                                ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-2 whitespace-nowrap"><?= htmlspecialchars($series['round'] ?? '-') ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap"><?= htmlspecialchars($opponentName) ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap font-semibold <?= $isThisTeamWinner ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= $resultText ?>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap"><?= htmlspecialchars($seriesScore) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            
            <?php if (!empty($playoffRoundsChart)): ?>
            <section class="chart-container min-h-[300px] md:min-h-[350px]">
                <h3 class="text-lg font-semibold mb-3 text-slate-700 text-center">Grafik Kemenangan Tim per Babak Playoff</h3>
                <canvas id="playoffRoundWinsChart"></canvas>
            </section>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-8 bg-white rounded-md shadow">
                <i class="fas fa-info-circle fa-3x text-slate-400 mb-3"></i>
                <p class="text-slate-600">
                    <?php 
                    if ($teamDetail && ($teamDetail['playoff'] === 'Y' || $teamDetail['playoff'] !== '-')) {
                        echo 'Tidak ada detail pertandingan playoff yang ditemukan untuk tim ini pada musim data ' . htmlspecialchars($year_param) . '.';
                    } else {
                        echo 'Tim tidak lolos playoff pada musim ' . htmlspecialchars($year_param) . '.';
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="text-center">
            <a href="teams_stats.php<?= isset($_GET['team_season']) ? '?team_season=' . htmlspecialchars($_GET['team_season']) : '' ?>" class="back-link">
                Kembali
            </a>
        </div>
    </div>

    <?php if (!empty($playoffRoundsChart)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctxPlayoff = document.getElementById('playoffRoundWinsChart')?.getContext('2d');
            if (ctxPlayoff) {
                new Chart(ctxPlayoff, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($playoffRoundsChart) ?>,
                        datasets: [{
                            label: 'Kemenangan Tim Ini di Babak',
                            data: <?= json_encode($playoffWinsInRoundChart) ?>,
                            backgroundColor: 'rgba(79, 70, 229, 0.7)', // Indigo
                            borderColor: 'rgba(79, 70, 229, 1)',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                    }
                });
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>