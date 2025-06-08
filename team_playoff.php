<?php
require_once 'db.php';
// include 'header.php';

$year_param = $_GET['year'] ?? null; // Ini akan menerima TAHUN AWAL MUSIM dari teams_stats.php
$tmID_param = $_GET['tmID'] ?? null;

if (!$year_param || !$tmID_param) {
    die("Parameter tahun (year) dan ID Tim (tmID) diperlukan.");
}
$year_param_int = (int)$year_param; // Ini adalah TAHUN AWAL MUSIM

// Tentukan tahun musim untuk ditampilkan (TAHUN AKHIR MUSIM)
$display_season_year = $year_param_int + 1;

// Ambil data tim dan data playoff yang disematkan dari coaches_collection
$pipelinePlayoff = [
    ['$unwind' => '$teams'],
    ['$unwind' => '$teams.team_season_details'],
    [
        '$match' => [
            // Query ke database tetap menggunakan TAHUN AWAL MUSIM ($year_param_int)
            'teams.team_season_details.year' => $year_param_int,
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
    $playoffDataForDisplay = $teamAndPlayoffData['playoff_series'] ?? [];
    if ($teamDetail) {
        $teamNameToDisplay = $teamDetail['name'] ?? $tmID_param;
    }
}

if (!$teamDetail) {
    die("Detail tim tidak ditemukan untuk ".htmlspecialchars($tmID_param)." musim yang dimulai ".htmlspecialchars($year_param).".");
}

// Siapkan data untuk chart playoff (jika ada)
$playoffRoundsChart = [];
$playoffWinsInRoundChart = [];
if (!empty($playoffDataForDisplay)) {
    // Sortir playoffDataForDisplay berdasarkan urutan round jika ada field urutan
    // Contoh jika ada field 'roundOrder' atau jika nama round bisa diurutkan secara logis
    // usort($playoffDataForDisplay, function($a, $b) {
    //     $roundOrder = ['CFR' => 1, 'CSF' => 2, 'CF' => 3, 'F' => 4]; // Sesuaikan dengan nama round Anda
    //     return ($roundOrder[$a['round']] ?? 99) <=> ($roundOrder[$b['round']] ?? 99);
    // });

    foreach ($playoffDataForDisplay as $playoff_series) {
        $isWinner = ($playoff_series['tmIDWinner'] ?? null) === $tmID_param;
        $roundLabel = ($playoff_series['round'] ?? 'N/A');
        
        // Hanya tambahkan jika round belum ada untuk menghindari duplikasi jika data tidak terstruktur dengan baik
        if (!in_array($roundLabel, array_column($playoffRoundsChart, 'label'))) { // Sedikit modifikasi untuk cek label
            $winsThisSeries = 0;
            if ($isWinner) {
                $winsThisSeries = (int)($playoff_series['W'] ?? 0);
            } else {
                $winsThisSeries = (int)($playoff_series['L'] ?? 0); // Jika kalah, tim ini menang sejumlah L game
            }
            $playoffRoundsChart[] = ['label' => $roundLabel, 'wins' => $winsThisSeries]; // Simpan sebagai objek
        }
    }
    // Jika perlu urutan spesifik untuk chart dan data tidak terurut:
    $roundOrderMap = ['CFR' => 1, 'CSF' => 2, 'CF' => 3, 'F' => 4]; // Sesuaikan
    usort($playoffRoundsChart, function($a, $b) use ($roundOrderMap) {
        return ($roundOrderMap[$a['label']] ?? 99) <=> ($roundOrderMap[$b['label']] ?? 99);
    });
    // Pisahkan lagi setelah diurutkan
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
        body { font-family: 'Montserrat', sans-serif; background-color: #f0f9ff; color: #1e293b; padding-bottom: 2rem;}
        .font-condensed { font-family: 'Roboto Condensed', sans-serif; }
        .container { max-width: 900px; margin: 2rem auto; background-color: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); }
        .header-title { color: #1e3a8a; font-size: 2.25rem; margin-bottom: 0.5rem;}
        .header-subtitle { color: #475569; font-size: 1.25rem; margin-bottom: 1.5rem; }
        .stat-card-playoff { background-color: #e0f2fe; border-radius: 0.75rem; padding: 1rem; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .stat-label-playoff { color: #075985; font-size: 0.875rem; display: block; margin-bottom: 0.25rem;}
        .stat-value-playoff { color: #0c4a6e; font-size: 1.5rem; font-weight: 700; }
        .section-heading { font-family: 'Roboto Condensed', sans-serif; font-size: 1.5rem; font-weight: 700; color: #1e40af; margin-bottom: 1rem; border-bottom: 2px solid #93c5fd; padding-bottom: 0.5rem;}
        .back-link { display: inline-block; margin-top: 2rem; padding: 0.625rem 1.25rem; background-color: #4f46e5; color: white; border-radius: 0.5rem; font-weight: 600; text-decoration: none; text-align: center; transition: background-color 0.3s ease; }
        .back-link:hover { background-color: #4338ca; }
        .chart-container { background-color: #fff; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.07); }
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
            <p class="header-subtitle">Detail Playoff Musim <?= $display_season_year // Tampilkan TAHUN AKHIR MUSIM ?></p>
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
                                <th class="px-4 py-3">Round</th>
                                <th class="px-4 py-3">Lawan</th>
                                <th class="px-4 py-3">Hasil (Tim Ini)</th>
                                <th class="px-4 py-3">Skor Seri (W-L)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php
                                // Sortir $playoffDataForDisplay sebelum ditampilkan di tabel jika perlu
                                // usort($playoffDataForDisplay, function($a, $b) use ($roundOrderMap) {
                                //    return ($roundOrderMap[$a['round']] ?? 99) <=> ($roundOrderMap[$b['round']] ?? 99);
                                // });
                            ?>
                            <?php foreach ($playoffDataForDisplay as $series): ?>
                                <?php
                                $isThisTeamWinner = ($series['tmIDWinner'] ?? null) === $tmID_param;
                                $opponentTeamID = $isThisTeamWinner ? ($series['tmIDLoser'] ?? 'N/A') : ($series['tmIDWinner'] ?? 'N/A');
                                $opponentName = $opponentTeamID; // Idealnya lookup nama tim
                                $resultText = $isThisTeamWinner ? "Menang" : "Kalah";
                                $seriesScore = $isThisTeamWinner ? (($series['W'] ?? 0) . " - " . ($series['L'] ?? 0)) : (($series['L'] ?? 0) . " - " . ($series['W'] ?? 0));
                                ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 whitespace-nowrap"><?= htmlspecialchars($series['round'] ?? '-') ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap"><?= htmlspecialchars($opponentName) ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold <?= $isThisTeamWinner ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= $resultText ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap"><?= htmlspecialchars($seriesScore) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php if (!empty($finalPlayoffLabels)): // Menggunakan variabel baru untuk chart ?>
            <section class="chart-container min-h-[300px] md:min-h-[350px] mt-8">
                <h3 class="text-lg font-semibold mb-4 text-slate-700 text-center">Grafik Kemenangan Tim per Babak Playoff</h3>
                <canvas id="playoffRoundWinsChart"></canvas>
            </section>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-10 bg-white rounded-md shadow">
                <i class="fas fa-info-circle fa-3x text-slate-400 mb-4"></i>
                <p class="text-slate-600 text-lg">
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

    <?php if (!empty($finalPlayoffLabels)): // Menggunakan variabel baru untuk chart ?>
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
                            backgroundColor: 'rgba(79, 70, 229, 0.7)', 
                            borderColor: 'rgba(79, 70, 229, 1)',
                            borderWidth: 1,
                            borderRadius: {topLeft: 4, topRight: 4}
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        indexAxis: <?= count($finalPlayoffLabels) > 4 ? "'y'" : "'x'" ?>,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
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
                            y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } },
                            x: { ticks: { /* autoSkip: false, maxRotation: 45, minRotation: 45 */ } }
                        }
                    }
                });
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>