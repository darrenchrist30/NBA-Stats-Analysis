<?php
include 'header.php'; 
require_once 'autoload.php';

use Laudis\Neo4j\ClientBuilder;

// --- CONFIG & CONNECTION FUNCTION (No Change) ---
$neo4j_connection_uri = 'neo4j://neo4j:neo4jpwd@localhost:7687'; 

function runQuery($query, $params = []) {
    global $neo4j_connection_uri;
    try {
        $client = ClientBuilder::create()->withDriver('default', $neo4j_connection_uri)->build();
        return $client->run($query, $params);
    } catch (Exception $e) {
        return ['error' => 'Gagal terhubung atau menjalankan query ke Neo4j: ' . $e->getMessage()];
    }
}

// --- DATA FETCHING ---

// QUERY 1: TOP 10 FACTORIES (No Change)
$topCollegesResult = runQuery(
    'MATCH (c:College)<-[:ATTENDED]-(p:Player)-[r:WAS_ALLSTAR_IN]->(asg:AllStarGame)
     WHERE c.name IS NOT NULL AND c.name <> "None"
     RETURN c.name AS university, count(r) AS total_allstar_appearances
     ORDER BY total_allstar_appearances DESC
     LIMIT 10'
);

// NEW: GET ALL COLLEGES FOR FILTERS
$allCollegesResult = runQuery('MATCH (c:College) WHERE c.name <> "None" RETURN c.name AS university ORDER BY c.name');
$collegesList = [];
if (isset($allCollegesResult) && !isset($allCollegesResult['error'])) {
    foreach ($allCollegesResult as $record) {
        $collegesList[] = $record->get('university');
    }
}

// NEW: GET SELECTED RIVALS FROM URL
$college1 = $_GET['college1'] ?? 'Duke'; // Default to Duke
$college2 = $_GET['college2'] ?? 'North Carolina'; // Default to North Carolina

// QUERY 2: DYNAMIC RIVALRY ANALYSIS
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

// Prepare data for Chart.js (No Change)
$topCollegesChartData = ['labels' => [], 'data' => []];
if (isset($topCollegesResult) && !isset($topCollegesResult['error'])) {
    foreach ($topCollegesResult as $record) {
        $topCollegesChartData['labels'][] = $record->get('university');
        $topCollegesChartData['data'][] = $record->get('total_allstar_appearances');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NBA College Pipeline Analysis</title>
    <style>
        .content-container { background-color: rgba(23, 23, 38, 0.7); border: 1px solid rgba(55, 65, 81, 0.4); border-radius: 0.75rem; padding: 2rem; backdrop-filter: blur(8px); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); }
        .rank-number { font-family: 'Teko', sans-serif; font-size: 1.5rem; line-height: 1; width: 2.5rem; text-align: center; }
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
        </div>
    <?php else: ?>

    <!-- Top 10 Factories Section (No Change) -->
    <section class="mb-20">
        <!-- ... your existing code for the top 10 chart and rankings ... -->
    </section>

    <!-- Interactive Rivalry Section -->
    <section>
        <div class="content-container">
            <h2 class="text-2xl font-semibold text-gray-200 font-rajdhani mb-2 text-center">Campus Rivalry on the Big Stage</h2>
            <p class="text-center text-gray-400 mb-8">Select two universities to see when their alumni faced off in an All-Star Game.</p>
            
            <!-- NEW: Filter Form -->
            <form method="GET" action="college_pipeline.php#rivalry-table" class="max-w-4xl mx-auto grid grid-cols-1 sm:grid-cols-3 gap-4 items-center mb-8 bg-gray-900/50 p-4 rounded-lg">
                <div class="sm:col-span-1">
                    <select name="college1" class="w-full bg-gray-800 border-gray-700 rounded-md p-2.5 text-white">
                        <?php foreach ($collegesList as $college): ?>
                            <option value="<?= htmlspecialchars($college) ?>" <?= ($college1 == $college) ? 'selected' : '' ?>><?= htmlspecialchars($college) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="text-center font-bold text-gray-400">VS</div>
                <div class="sm:col-span-1">
                     <select name="college2" class="w-full bg-gray-800 border-gray-700 rounded-md p-2.5 text-white">
                        <?php foreach ($collegesList as $college): ?>
                            <option value="<?= htmlspecialchars($college) ?>" <?= ($college2 == $college) ? 'selected' : '' ?>><?= htmlspecialchars($college) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                 <button type="submit" class="w-full sm:col-span-3 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition">Analyze Rivalry</button>
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
    <!-- ... your footer code ... -->
</footer>

<script>
    // ... your Chart.js script (no change needed) ...
</script>

</body>
</html>