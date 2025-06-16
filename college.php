<?php
include 'header.php'; 
require_once 'autoload.php';

use Laudis\Neo4j\ClientBuilder;

// --- KONFIGURASI KONEKSI NEO4J ---
$neo4j_connection_uri = 'neo4j://neo4j:neo4jpwd@localhost:7687'; 

// --- FUNGSI UNTUK MENJALANKAN QUERY ---
function runQuery($query, $params = []) {
    global $neo4j_connection_uri;
    try {
        $client = ClientBuilder::create()->withDriver('default', $neo4j_connection_uri)->build();
        return $client->run($query, $params);
    } catch (Exception $e) {
        return ['error' => 'Gagal terhubung atau menjalankan query ke Neo4j: ' . $e->getMessage()];
    }
}

// --- PENGAMBILAN DATA ---

// PERBAIKAN: QUERY 1 dengan filter yang lebih ketat
$topCollegesResult = runQuery(
    'MATCH (c:College)<-[:ATTENDED]-(p:Player)-[r:WAS_ALLSTAR_IN]->(asg:AllStarGame)
     WHERE c.name <> "None" AND c.name <> "" AND c.name IS NOT NULL
     RETURN c.name AS university, count(r) AS total_allstar_appearances
     ORDER BY total_allstar_appearances DESC
     LIMIT 10'
);

// BARU: Ambil semua nama universitas untuk dropdown filter
$allCollegesResult = runQuery('MATCH (c:College) WHERE c.name <> "None" AND c.name <> "" AND c.name IS NOT NULL RETURN c.name AS university ORDER BY c.name');
$collegesList = [];
if (isset($allCollegesResult) && !isset($allCollegesResult['error'])) {
    foreach ($allCollegesResult as $record) {
        $collegesList[] = $record->get('university');
    }
}

// BARU: Baca universitas yang dipilih dari URL, atau gunakan default
$college1 = isset($_GET['college1']) && in_array($_GET['college1'], $collegesList) ? $_GET['college1'] : 'Duke';
$college2 = isset($_GET['college2']) && in_array($_GET['college2'], $collegesList) ? $_GET['college2'] : 'North Carolina';

// PERBAIKAN: QUERY 2 Dibuat Dinamis
$rivalryResult = runQuery(
    'MATCH (c1:College {name: $college1_name}), (c2:College {name: $college2_name})
     MATCH (p1:Player)-[:ATTENDED]->(c1)
     MATCH (p2:Player)-[:ATTENDED]->(c2)
     MATCH (p1)-[:WAS_ALLSTAR_IN]->(asg:AllStarGame)<-[:WAS_ALLSTAR_IN]-(p2)
     WITH asg, c1, c2, collect(DISTINCT p1.name) AS players1, collect(DISTINCT p2.name) AS players2
     RETURN asg.year AS all_star_year, 
            c1.name AS college1_name, players1, 
            c2.name AS college2_name, players2
     ORDER BY all_star_year DESC',
    ['college1_name' => $college1, 'college2_name' => $college2]
);

// --- PERBAIKAN: Menyiapkan data untuk Chart.js dengan lebih teliti ---
$topCollegesChartData = ['labels' => [], 'data' => []];
if (isset($topCollegesResult) && !isset($topCollegesResult['error'])) {
    foreach ($topCollegesResult as $record) {
        $university = $record->get('university');
        $appearances = $record->get('total_allstar_appearances');
        // Pastikan bukan "None" dan ada data
        if ($university !== 'None' && $university !== '' && $appearances > 0) {
            $topCollegesChartData['labels'][] = $university;
            $topCollegesChartData['data'][] = (int)$appearances;
        }
    }
}

// Debug: Uncomment untuk debugging
// echo "<pre>Chart Data: " . print_r($topCollegesChartData, true) . "</pre>";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NBA College Pipeline Analysis</title>
    <!-- PERBAIKAN: Tambahkan Chart.js library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        .content-container { background-color: rgba(23, 23, 38, 0.7); border: 1px solid rgba(55, 65, 81, 0.4); border-radius: 0.75rem; padding: 2rem; backdrop-filter: blur(8px); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); }
        .rank-number { font-family: 'Teko', sans-serif; font-size: 1.5rem; line-height: 1; width: 2.5rem; text-align: center; }
        /* Style untuk dropdown agar seragam */
        select { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239CA3AF' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; -webkit-appearance: none; -moz-appearance: none; appearance: none; }
    </style>
</head>
<body class="antialiased">

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <header class="text-center mb-16">
        <h1 class="text-4xl md:text-5xl font-bold text-gray-100 font-teko tracking-wide uppercase">College Pipeline Analysis</h1>
        <p class="text-lg text-gray-400 mt-2 max-w-3xl mx-auto">Discover which universities are the true "All-Star Factories" of the NBA, producing the most elite talent for the league's biggest stage.</p>
    </header>

    <?php if (isset($topCollegesResult['error'])): ?>
        <div class="bg-red-900 border border-red-600 text-white px-4 py-3 rounded-lg relative max-w-4xl mx-auto" role="alert">
            <strong class="font-bold">Database Connection Error!</strong>
            <span class="block sm:inline"><?= htmlspecialchars($topCollegesResult['error']) ?></span>
            <p class="mt-2 text-sm text-red-200">Please ensure your Neo4j database is running and the connection details (password) in this file are correct.</p>
        </div>
    <?php else: ?>

    <section class="mb-20">
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
            <div class="lg:col-span-3 content-container">
                <h2 class="text-2xl font-semibold text-gray-200 font-rajdhani mb-4">Top 10 All-Star Factories</h2>
                <div class="relative h-96 md:h-[450px]">
                    <canvas id="topCollegesChart"></canvas>
                </div>
            </div>
            <div class="lg:col-span-2 content-container">
                <h2 class="text-2xl font-semibold text-gray-200 font-rajdhani mb-4">The Rankings</h2>
                <ol class="space-y-3">
                    <?php if (count($topCollegesChartData['labels']) > 0): ?>
                        <?php $rank = 1; ?>
                        <?php foreach ($topCollegesResult as $record): ?>
                            <?php 
                            $university = $record->get('university');
                            $appearances = $record->get('total_allstar_appearances');
                            // Skip "None" entries dalam ranking juga
                            if ($university === 'None' || $university === '' || $appearances <= 0) continue;
                            ?>
                            <li class="flex items-center justify-between text-lg p-2 rounded-md transition hover:bg-gray-800/50">
                                <div class="flex items-center">
                                    <span class="rank-number text-gray-400"><?= $rank ?></span>
                                    <span class="text-gray-200 ml-4"><?= htmlspecialchars($university) ?></span>
                                </div>
                                <span class="font-bold font-teko text-3xl text-blue-400"><?= $appearances ?></span>
                            </li>
                            <?php $rank++; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="text-gray-500">No ranking data found. Please run the ETL script.</li>
                    <?php endif; ?>
                </ol>
            </div>
        </div>
    </section>

    <section>
        <div class="content-container">
            <h2 class="text-2xl font-semibold text-gray-200 font-rajdhani mb-2 text-center">Campus Rivalry on the Big Stage</h2>
            <p class="text-center text-gray-400 mb-8">Select two universities to see when their alumni faced off in an All-Star Game.</p>
            
            <!-- BARU: Filter Form Dinamis -->
            <form method="GET" action="college.php#rivalry-table" class="max-w-4xl mx-auto grid grid-cols-1 sm:grid-cols-5 gap-4 items-center mb-8 bg-gray-900/50 p-4 rounded-lg">
                <div class="sm:col-span-2">
                    <label for="college1" class="text-xs text-gray-400 mb-1 block">University 1</label>
                    <select id="college1" name="college1" class="w-full bg-gray-800 border-gray-700 rounded-md p-2.5 text-white">
                        <?php foreach ($collegesList as $college): ?>
                            <option value="<?= htmlspecialchars($college) ?>" <?= ($college1 == $college) ? 'selected' : '' ?>><?= htmlspecialchars($college) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="text-center font-bold text-gray-400 pt-5">VS</div>
                <div class="sm:col-span-2">
                    <label for="college2" class="text-xs text-gray-400 mb-1 block">University 2</label>
                     <select id="college2" name="college2" class="w-full bg-gray-800 border-gray-700 rounded-md p-2.5 text-white">
                        <?php foreach ($collegesList as $college): ?>
                            <option value="<?= htmlspecialchars($college) ?>" <?= ($college2 == $college) ? 'selected' : '' ?>><?= htmlspecialchars($college) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sm:col-span-5">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-md transition uppercase tracking-wider">Analyze Rivalry</button>
                </div>
            </form>

            <div id="rivalry-table" class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-800/50">
                        <tr class="text-left text-xs text-gray-400 uppercase tracking-wider">
                            <th class="px-4 py-3 font-semibold w-1/5 text-center">All-Star Year</th>
                            <th class="px-4 py-3 font-semibold w-2/5"><?= htmlspecialchars($college1) ?> Alumni</th>
                            <th class="px-4 py-3 font-semibold w-2/5"><?= htmlspecialchars($college2) ?> Alumni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php if (isset($rivalryResult) && count($rivalryResult) > 0): ?>
                            <?php foreach ($rivalryResult as $record): ?>
                            <tr class="hover:bg-gray-800/40">
                                <td class="px-4 py-4 font-bold text-center text-blue-400 font-teko text-3xl"><?= $record->get('all_star_year') ?></td>
                                <td class="px-4 py-4 text-gray-300 leading-relaxed"><?= implode(', ', $record->get('players1')->toArray()) ?></td>
                                <td class="px-4 py-4 text-gray-300 leading-relaxed"><?= implode(', ', $record->get('players2')->toArray()) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center p-8 text-gray-500">No All-Star games found for this specific rivalry. Try different universities.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    
    <?php endif; ?>

</div>

<footer class="text-center mt-16 py-8 border-t border-gray-800/30">
    <p class="text-xs text-gray-500">© <?= date("Y") ?> NBA Universe Dashboard. All Rights Reserved by You.</p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const chartData = <?= json_encode($topCollegesChartData) ?>;
    
    // Debug log
    console.log('Chart Data:', chartData);
    
    if (chartData && chartData.labels && chartData.labels.length > 0) {
        const ctx = document.getElementById('topCollegesChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Total All-Star Appearances',
                        data: chartData.data,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgba(96, 165, 250, 1)',
                        borderWidth: 1,
                        borderRadius: 4,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { 
                            beginAtZero: true, 
                            grid: { color: 'rgba(255, 255, 255, 0.1)' }, 
                            ticks: { color: '#9CA3AF', font: { size: 12 } } 
                        },
                        y: { 
                            grid: { display: false }, 
                            ticks: { color: '#D1D5DB', font: { size: 14 } } 
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#111827', 
                            titleColor: '#E5E7EB', 
                            bodyColor: '#D1D5DB', 
                            boxPadding: 6,
                            callbacks: {
                                label: function(context) { 
                                    return ` ${context.formattedValue} Appearances`; 
                                }
                            }
                        }
                    }
                }
            });
        }
    } else {
        console.log('No chart data available or data is empty');
        const chartContainer = document.getElementById('topCollegesChart')?.parentElement;
        if(chartContainer) {
            chartContainer.innerHTML = '<div class="flex items-center justify-center h-full text-gray-500"><p>No chart data available. Please check your database connection.</p></div>';
        }
    }
});
</script>

</body>
</html>