<?php
require_once 'db.php';

$year = $_GET['year'] ?? null;
$teamName = $_GET['name'] ?? null;

if (!$year || !$teamName) {
    die("Parameter tahun dan nama tim diperlukan.");
}

$teamData = $teams->findOne(['year' => (int)$year, 'name' => $teamName]);

if (!$teamData) {
    die("Data tim tidak ditemukan.");
}

// Data untuk diagram donat
$offenseData = [
    'FGM' => $teamData['o_fgm'] ?? 0,
    'FGA' => $teamData['o_fga'] ?? 0,
    'FTM' => $teamData['o_ftm'] ?? 0,
    'FTA' => $teamData['o_fta'] ?? 0,
    '3PM' => $teamData['o_3pm'] ?? 0,
    '3PA' => $teamData['o_3pa'] ?? 0,
    'O-Reb' => $teamData['o_oreb'] ?? 0,
    'D-Reb' => $teamData['o_dreb'] ?? 0,
    'Reb' => $teamData['o_reb'] ?? 0,
    'Asts' => $teamData['o_asts'] ?? 0,
    'PF' => $teamData['o_pf'] ?? 0,
    'Stl' => $teamData['o_stl'] ?? 0,
    'TO' => $teamData['o_to'] ?? 0,
    'Blk' => $teamData['o_blk'] ?? 0,
    'Pts' => $teamData['o_pts'] ?? 0,
    'Tm Reb' => $teamData['o_tmRebound'] ?? 0,
];

$defenseData = [
    'FGM' => $teamData['d_fgm'] ?? 0,
    'FGA' => $teamData['d_fga'] ?? 0,
    'FTM' => $teamData['d_ftm'] ?? 0,
    'FTA' => $teamData['d_fta'] ?? 0,
    '3PM' => $teamData['d_3pm'] ?? 0,
    '3PA' => $teamData['d_3pa'] ?? 0,
    'O-Reb' => $teamData['d_oreb'] ?? 0,
    'D-Reb' => $teamData['d_dreb'] ?? 0,
    'Reb' => $teamData['d_reb'] ?? 0,
    'Asts' => $teamData['d_asts'] ?? 0,
    'PF' => $teamData['d_pf'] ?? 0,
    'Stl' => $teamData['d_stl'] ?? 0,
    'TO' => $teamData['d_to'] ?? 0,
    'Blk' => $teamData['d_blk'] ?? 0,
    'Pts' => $teamData['d_pts'] ?? 0,
    'Tm Reb' => $teamData['d_tmRebound'] ?? 0,
];

// Fungsi untuk menghasilkan warna acak
function randomColor() {
    return '#' . str_pad(dechex(rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
}

// Siapkan data untuk chart.js
function prepareChartData($data) {
    $labels = array_keys($data);
    $values = array_values($data);
    $backgroundColors = array_map(function() { return randomColor(); }, $labels);
    return [
        'labels' => $labels,
        'datasets' => [
            [
                'data' => $values,
                'backgroundColor' => $backgroundColors,
                'hoverOffset' => 4
            ]
        ]
    ];
}

$offenseChartData = prepareChartData($offenseData);
$defenseChartData = prepareChartData($defenseData);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title class="text-xl font-bold">Detail Tim: <?= htmlspecialchars($teamName) ?> (<?= $year ?>)</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #f0fdfa 0%, #ede7f6 100%);
            color: #1e293b;
            padding-top: 2rem;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background-color: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        h1, h2 {
            color: #4c3d98; /* Indigo-700 */
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .chart-row {
            display: flex;
            justify-content: space-around;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 20px;
        }
        .chart-container {
            width: calc(50% - 10px);
            min-width: 300px;
            background-color: #f9f7f7;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        h3 {
            text-align: center;
            color: #5e548e;
            margin-bottom: 1rem;
        }
        .back-button {
            display: block;
            margin-top: 2rem;
            padding: 0.75rem 1.5rem;
            background-color: #4c3d98;
            color: white;
            border-radius: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s ease;
        }
        .back-button:hover {
            background-color: #3f37c9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-2xl font-bold">Detail Tim: <?= htmlspecialchars($teamName) ?> (<?= $year ?>)</h1>

        <div class="chart-row">
            <div class="chart-container font-bold text-blue-800 text-lg">
                <h3>Offensive Stats</h3>
                <canvas id="offenseChart"></canvas>
            </div>
            <div class="chart-container font-bold text-blue-800 text-lg">
                <h3>Defensive Stats</h3>
                <canvas id="defenseChart"></canvas>
            </div>
        </div>

        <a href="teams_stats.php<?= isset($_GET['team_season']) ? '?team_season=' . $_GET['team_season'] : '' ?>" class="back-button">Kembali ke Performa Tim</a>
    </div>

    <script>
        const offenseData = <?= json_encode($offenseChartData) ?>;
        const defenseData = <?= json_encode($defenseChartData) ?>;

        const offenseCtx = document.getElementById('offenseChart').getContext('2d');
        const defenseCtx = document.getElementById('defenseChart').getContext('2d');

        new Chart(offenseCtx, {
            type: 'doughnut',
            data: offenseData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: true,
                        text: 'Offensive Breakdown'
                    }
                }
            }
        });

        new Chart(defenseCtx, {
            type: 'doughnut',
            data: defenseData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: true,
                        text: 'Defensive Breakdown'
                    }
                }
            }
        });
    </script>
</body>
</html>