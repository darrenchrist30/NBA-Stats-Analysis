<?php
require_once 'db.php';
include 'header.php';

// Ambil filter dari request GET
$filterSeason = $_GET['season'] ?? '';
$filterTeam = $_GET['team'] ?? '';
$filterPos = $_GET['pos'] ?? '';
$filterPlayerName = $_GET['playerName'] ?? ''; // Tambah filter nama

// Ambil data unik untuk filter dropdown
$seasons = $playersTeams->distinct('year');
rsort($seasons); // Urutkan musim terbaru di atas

$teamIDs = $playersTeams->distinct('tmID');
$teamDisplayNames = [];
if (!empty($teamIDs)) { // Check if teamIDs is not empty and is an array
    if (is_array($teamIDs)) { // Ensure $teamIDs is an array before using $in
        $teamDetails = $teams->find(['tmID' => ['$in' => $teamIDs]], ['projection' => ['tmID' => 1, 'name' => 1, 'year' => 1]]);
        $latestTeamNameMap = [];
        foreach ($teamDetails as $td) {
            if (!isset($latestTeamNameMap[$td['tmID']]) || $td['year'] > $latestTeamNameMap[$td['tmID']]['year']) {
                $latestTeamNameMap[$td['tmID']] = ['name' => $td['name'], 'year' => $td['year']];
            }
        }
        foreach ($teamIDs as $id) {
            $teamDisplayNames[$id] = $latestTeamNameMap[$id]['name'] ?? $id;
        }
        asort($teamDisplayNames);
    } else {
        // Handle case where $teamIDs is not an array, though distinct should return one
        $teamIDs = []; // Ensure it's an empty array if it was not an array
    }
}


$positions = $players->distinct('pos');
$positions = array_filter($positions, fn($value) => !is_null($value) && $value !== ''); // Hapus null atau string kosong
sort($positions);

// Bangun filter query MongoDB
$query = [];
if ($filterSeason) $query['year'] = (int)$filterSeason;
if ($filterTeam) $query['tmID'] = $filterTeam;

$playerIDsForFilter = null;

if ($filterPos || $filterPlayerName) {
    $playerFilterQuery = [];

    if ($filterPos) {
        $playerFilterQuery['pos'] = $filterPos;
    }

    if ($filterPlayerName) {
        $playerFilterQuery['$or'] = [
            ['firstName' => new MongoDB\BSON\Regex($filterPlayerName, 'i')],
            ['lastName' => new MongoDB\BSON\Regex($filterPlayerName, 'i')]
        ];
    }

    $matchingPlayers = $players->find($playerFilterQuery, ['projection' => ['playerID' => 1]]);
    $playerIDsForFilter = [];

    foreach ($matchingPlayers as $p) {
        if (isset($p['playerID']) && is_string($p['playerID'])) {
            $playerIDsForFilter[] = $p['playerID'];
        }
    }

    if (empty($playerIDsForFilter)) {
        // agar tidak error, pakai value dummy yang tidak mungkin cocok
        $query['playerID'] = ['$in' => ['__NO_MATCH__']];
    } else {
        $query['playerID'] = ['$in' => array_values(array_unique($playerIDsForFilter))];
    }
}

// Query utama ke collection players_teams
$options = ['limit' => 250, 'sort' => ['year' => -1, 'points' => -1]];
$cursor = $playersTeams->find($query, $options);

// Ambil hasil dan playerID
$data = [];
$playerIDsFromCursor = [];

foreach ($cursor as $item) {
    $data[] = $item;
    if (isset($item['playerID']) && is_string($item['playerID'])) {
        $playerIDsFromCursor[] = $item['playerID'];
    }
}

// Bersihkan dan unik
$playerIDsFromCursor = array_values(array_unique(array_filter($playerIDsFromCursor, 'is_string')));

// Ambil detail pemain
$playersDetailsMap = [];

if (!empty($playerIDsFromCursor)) {
    $playersInfoCursor = $players->find(
        ['playerID' => ['$in' => $playerIDsFromCursor]],
        ['projection' => ['playerID' => 1, 'firstName' => 1, 'lastName' => 1, 'pos' => 1]]
    );

    foreach ($playersInfoCursor as $pInfo) {
        $playersDetailsMap[$pInfo['playerID']] = $pInfo;
    }
}

// Tambahkan nama dan posisi ke $data (tanpa mengubah struktur asli $item dari playersTeams)
$processedData = [];
foreach($data as $item) {
    $playerDetail = isset($item['playerID']) && isset($playersDetailsMap[$item['playerID']]) ? $playersDetailsMap[$item['playerID']] : null;
    $tempItem = $item; 
    $tempItem['playerName'] = trim(($playerDetail['firstName'] ?? '') . ' ' . ($playerDetail['lastName'] ?? ($item['playerID'] ?? 'Unknown')));
    $tempItem['pos'] = $playerDetail['pos'] ?? '-';
    $processedData[] = $tempItem;
}
$data = $processedData; 
// ------------------------------------------------------------------------------------
// AKHIR DARI LOGIKA PENGAMBILAN DATA PHP YANG TIDAK DIUBAH

// Fungsi bantu cari nama pemain lengkap dari playerID (tetap ada jika dipanggil di tempat lain, tapi kita sudah integrasikan)
function getPlayerName($playerID, $playersCollection) {
    $p = $playersCollection->findOne(['playerID' => $playerID]);
    if ($p) {
        return ($p['firstName'] ?? '') . ' ' . ($p['lastName'] ?? '');
    }
    return $playerID;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Player Performance Dashboard - NBA Stats</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #e0e7ff; /* Even Lighter Gray background for overall page */ }
        .stat-card-enhanced {
            background-color: white;
            border-radius: 0.75rem; /* 12px */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            padding: 1.25rem; /* p-5 */
        }
        .stat-card-enhanced:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.07), 0 4px 6px -4px rgba(0, 0, 0, 0.07);
        }
        .table-container { background-color: white; border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .table-scroll { max-height: 500px; overflow-y: auto; }
        ::-webkit-scrollbar { width: 6px; height: 6px;}
        ::-webkit-scrollbar-track { background: #edf2f7; border-radius: 10px;}
        ::-webkit-scrollbar-thumb { background: #a0aec0; border-radius: 10px;}
        ::-webkit-scrollbar-thumb:hover { background: #718096; }
        .chart-card {
            background-color: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        select, input[type="text"] {
            border-color: #d1d5db; /* gray-300 */
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
        }
        select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
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
            border-color: #4f46e5; /* Indigo-600 */
            box-shadow: 0 0 0 3px rgba(165, 180, 252, 0.4); /* Indigo-300 with opacity */
        }
        .btn-primary {
            background-color: #4f46e5; /* indigo-600 */
            color: white;
            font-weight: 600; /* semibold */
            padding: 0.5rem 1rem; /* py-2 px-4 */
            border-radius: 0.375rem; /* rounded-md */
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
            transition: background-color 0.15s ease-in-out;
        }
        .btn-primary:hover {
            background-color: #4338ca; /* indigo-700 */
        }
        .btn-primary:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            box-shadow: 0 0 0 3px rgba(165, 180, 252, 0.4);
        }
    </style>
</head>
<body class="text-gray-800 antialiased">

    <!-- Main Content Area -->
    <div class="max-w-screen-2xl mx-auto p-4 md:p-6 lg:p-8">

            <header class="mb-6 md:mb-8">
                <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Player Performance Dashboard</h1>
                <p class="text-sm text-slate-500 mt-1">Dive into NBA player statistics with interactive filters and visualizations.</p>
            </header>

            <div class="bg-white p-5 md:p-6 rounded-xl shadow-lg mb-8 sticky top-4 z-20 border border-gray-300">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-x-4 gap-y-4 items-end">
            <div>
                <label for="season" class="block text-xs font-medium text-gray-600 mb-1.5">Season</label>
                <select name="season" id="season" class="w-full border border-gray-400 rounded-md text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 appearance-none">
                    <option value="">All Seasons</option>
                    <?php foreach ($seasons as $season): ?>
                        <option value="<?= htmlspecialchars($season) ?>" <?= ($filterSeason == $season) ? 'selected' : '' ?>><?= htmlspecialchars($season) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="team" class="block text-xs font-medium text-gray-600 mb-1.5">Team</label>
                <select name="team" id="team" class="w-full border border-gray-400 rounded-md text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 appearance-none">
                    <option value="">All Teams</option>
                    <?php foreach ($teamDisplayNames as $tmID => $name): ?>
                        <option value="<?= htmlspecialchars($tmID) ?>" <?= ($filterTeam == $tmID) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="pos" class="block text-xs font-medium text-gray-600 mb-1.5">Position</label>
                <select name="pos" id="pos" class="w-full border border-gray-400 rounded-md text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500 appearance-none">
                    <option value="">All Positions</option>
                    <?php foreach ($positions as $pos): ?>
                        <option value="<?= htmlspecialchars($pos) ?>" <?= ($filterPos == $pos) ? 'selected' : '' ?>><?= htmlspecialchars($pos) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="playerName" class="block text-xs font-medium text-gray-600 mb-1.5">Player Name</label>
                <input type="text" name="playerName" id="playerName" class="w-full border border-gray-400 rounded-md text-sm py-2 px-3 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., LeBron James" value="<?= htmlspecialchars($filterPlayerName) ?>">
            </div>
            <button type="submit" class="btn-primary w-full flex items-center justify-center">
                <i class="fas fa-sliders-h mr-2 fa-sm"></i> Apply Filters
            </button>
        </form>
    </div>

        <!-- Stat Summary Cards -->
        <?php
        // Kalkulasi KPI (SAMA SEPERTI KODE ASLI ANDA)
        $totalPoints = $totalAssists = $totalRebounds = $totalFG = $totalFGA = $totalGamesAll = 0;
        $totalSteals = $totalBlocks = $totalTurnovers = 0;
        $totalPlayerSeasonEntries = count($data); // Jumlah entri setelah filter

        foreach ($data as $row) { // Menggunakan $data yang sudah diproses (punya playerName, pos)
            $totalPoints += $row['points'] ?? 0;
            $totalAssists += $row['assists'] ?? 0;
            $totalRebounds += $row['rebounds'] ?? 0;
            $totalFG += $row['fgMade'] ?? 0;
            $totalFGA += $row['fgAttempted'] ?? 0;
            $totalGamesAll += $row['GP'] ?? 0; // Total Games Played dari semua entri
            $totalSteals += $row['steals'] ?? 0;
            $totalBlocks += $row['blocks'] ?? 0;
            $totalTurnovers += $row['turnovers'] ?? 0;
        }
        // Averages per game (menggunakan total GP dari semua entri yang difilter)
        $avgPoints = $totalGamesAll > 0 ? round($totalPoints / $totalGamesAll, 1) : 0;
        $avgAssists = $totalGamesAll > 0 ? round($totalAssists / $totalGamesAll, 1) : 0;
        $avgRebounds = $totalGamesAll > 0 ? round($totalRebounds / $totalGamesAll, 1) : 0;
        $avgSteals = $totalGamesAll > 0 ? round($totalSteals / $totalGamesAll, 1) : 0;
        $avgBlocks = $totalGamesAll > 0 ? round($totalBlocks / $totalGamesAll, 1) : 0;
        $avgTurnovers = $totalGamesAll > 0 ? round($totalTurnovers / $totalGamesAll, 1) : 0;
        // Avg Games Played per Player-Season Entry
        $avgGamePlayedPerEntry = $totalPlayerSeasonEntries > 0 ? round($totalGamesAll / $totalPlayerSeasonEntries, 1) : 0;

        $statCardsData = [
            ['label' => 'Avg Points/Game', 'value' => $avgPoints, 'color' => 'text-blue-600', 'icon' => 'fa-solid fa-basketball', 'bg' => 'bg-blue-50'],
            ['label' => 'Avg Assists/Game', 'value' => $avgAssists, 'color' => 'text-green-600', 'icon' => 'fa-solid fa-hands-helping', 'bg' => 'bg-green-50'],
            ['label' => 'Avg Rebounds/Game', 'value' => $avgRebounds, 'color' => 'text-amber-600', 'icon' => 'fa-solid fa-people-carry-box', 'bg' => 'bg-amber-50'],
            ['label' => 'Avg Steals/Game', 'value' => $avgSteals, 'color' => 'text-purple-600', 'icon' => 'fa-solid fa-user-secret', 'bg' => 'bg-purple-50'],
            ['label' => 'Avg Blocks/Game', 'value' => $avgBlocks, 'color' => 'text-orange-600', 'icon' => 'fa-solid fa-shield-halved', 'bg' => 'bg-orange-50'],
            ['label' => 'Avg Turnovers/Game', 'value' => $avgTurnovers, 'color' => 'text-red-600', 'icon' => 'fa-solid fa-recycle', 'bg' => 'bg-red-50'],
            ['label' => 'Avg GP/Entry', 'value' => $avgGamePlayedPerEntry, 'color' => 'text-teal-600', 'icon' => 'fa-solid fa-calendar-check', 'bg' => 'bg-teal-50'],
            ['label' => 'Filtered Entries', 'value' => $totalPlayerSeasonEntries, 'color' => 'text-indigo-600', 'icon' => 'fa-solid fa-list-ol', 'bg' => 'bg-indigo-50'],
        ];
        ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5 mb-8">
            <?php foreach ($statCardsData as $card): ?>
            <div class="stat-card-enhanced flex items-center space-x-4">
                <div class="p-3.5 rounded-full <?= $card['bg'] ?> <?= $card['color'] ?>">
                     <i class="<?= $card['icon'] ?> fa-fw text-xl"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wider"><?= $card['label'] ?></p>
                    <p class="text-2xl font-semibold <?= $card['color'] ?> mt-0.5"><?= $card['value'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Data Table Section -->
        <div class="table-container p-0 mb-8">
            <div class="px-5 py-4 border-b border-gray-200 flex justify-between items-center">
                 <h2 class="text-lg font-semibold text-slate-700">Player Statistics Breakdown</h2>
                 <?php if ($totalPlayerSeasonEntries > 0): ?>
                    <span class="text-xs font-medium text-gray-500">
                        Showing <?= min($totalPlayerSeasonEntries, 250) ?> entries
                        <?= ($totalPlayerSeasonEntries > 250) ? " (top 250)" : "" ?>
                    </span>
                 <?php endif; ?>
            </div>
            <div class="overflow-x-auto table-scroll">
                <table class="min-w-full table-auto text-sm">
                    <thead class="sticky top-0 bg-slate-100 z-10">
                        <tr class="text-left text-xs text-slate-500 uppercase tracking-wider">
                            <th class="px-4 py-3 font-semibold">Player</th>
                            <th class="px-4 py-3 font-semibold">Team</th>
                            <th class="px-4 py-3 font-semibold">Pos</th>
                            <th class="px-4 py-3 font-semibold">Season</th>
                            <th class="px-4 py-3 font-semibold text-right">GP</th>
                            <th class="px-4 py-3 font-semibold text-right">Pts</th>
                            <th class="px-4 py-3 font-semibold text-right">Ast</th>
                            <th class="px-4 py-3 font-semibold text-right">Reb</th>
                            <th class="px-4 py-3 font-semibold text-right">Stl</th>
                            <th class="px-4 py-3 font-semibold text-right">Blk</th>
                            <th class="px-4 py-3 font-semibold text-right">TO</th>
                            <th class="px-4 py-3 font-semibold text-right">FG%</th>
                            <th class="px-4 py-3 font-semibold text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($data) > 0): ?>
                            <?php foreach ($data as $idx => $row): // Menggunakan $data yang sudah diproses
                                $fgPercent = 0;
                                if (isset($row['fgAttempted']) && $row['fgAttempted'] > 0 && isset($row['fgMade'])) {
                                    $fgPercent = round(($row['fgMade'] / $row['fgAttempted']) * 100, 1);
                                }
                                $rowClass = $idx % 2 == 0 ? 'bg-white' : 'bg-slate-50/70';
                                $teamNameForDisplay = $teamDisplayNames[$row['tmID']] ?? $row['tmID'];
                            ?>
                            <tr class="<?= $rowClass ?> hover:bg-indigo-50/40 transition-colors duration-100">
                                <td class="px-4 py-2.5 font-medium text-slate-700 whitespace-nowrap"><?= htmlspecialchars($row['playerName']) ?></td>
                                <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap"><?= htmlspecialchars($teamNameForDisplay) ?></td>
                                <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap text-center"><?= htmlspecialchars($row['pos']) ?></td>
                                <td class="px-4 py-2.5 text-slate-600 whitespace-nowrap text-center"><?= htmlspecialchars($row['year']) ?></td>
                                <td class="px-4 py-2.5 text-slate-600 text-right whitespace-nowrap"><?= htmlspecialchars($row['GP'] ?? '-') ?></td>
                                <td class="px-4 py-2.5 text-slate-700 text-right whitespace-nowrap font-semibold"><?= htmlspecialchars($row['points'] ?? '-') ?></td>
                                <td class="px-4 py-2.5 text-slate-600 text-right whitespace-nowrap"><?= htmlspecialchars($row['assists'] ?? '-') ?></td>
                                <td class="px-4 py-2.5 text-slate-600 text-right whitespace-nowrap"><?= htmlspecialchars($row['rebounds'] ?? '-') ?></td>
                                <td class="px-4 py-2.5 text-slate-600 text-right whitespace-nowrap"><?= htmlspecialchars($row['steals'] ?? '-') ?></td>
                                <td class="px-4 py-2.5 text-slate-600 text-right whitespace-nowrap"><?= htmlspecialchars($row['blocks'] ?? '-') ?></td>
                                <td class="px-4 py-2.5 text-slate-600 text-right whitespace-nowrap"><?= htmlspecialchars($row['turnovers'] ?? '-') ?></td>
                                <td class="px-4 py-2.5 text-slate-600 text-right whitespace-nowrap"><?= $fgPercent ?>%</td>
                                <td class="px-4 py-2.5 text-center whitespace-nowrap">
                                    <a href="player_season_detail.php?playerID=<?= urlencode($row['playerID'] ?? '') ?>&year=<?= $row['year'] ?? '' ?>" 
                                       class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold hover:underline py-1 px-1.5 rounded-md transition-colors">
                                        Details <i class="fas fa-angle-right fa-xs ml-0.5"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="13" class="text-center p-10 text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-empty-set fa-3x text-slate-300 mb-4"></i> <!-- Changed icon for variety -->
                                    <p class="font-semibold text-slate-600">No Player Data Found</p>
                                    <p class="text-sm">Please adjust your filters or try a different search.</p>
                                </div>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
             <?php if ($totalPlayerSeasonEntries > 250): ?>
                <div class="px-5 py-3 border-t border-gray-200 text-xs text-center text-slate-400">
                    Showing the top 250 results. Refine your search for more specific data.
                </div>
            <?php endif; ?>
        </div>

        <!-- Chart Section -->
        <?php if (count($data) > 0): ?>
        <section class="mt-10">
             <div class="px-1 py-4 mb-2">
                <h2 class="text-xl font-semibold text-slate-700 text-center">Visual Insights</h2>
                <p class="text-sm text-slate-500 text-center mt-1.5">Key statistics visualized for the top 15 filtered player-seasons.</p>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="chart-card min-h-[380px] md:min-h-[420px]">
                    <h3 class="text-base font-semibold mb-4 text-slate-600">Player Core Stats Comparison</h3>
                    <canvas id="barChartPlayers"></canvas>
                </div>
                <div class="chart-card min-h-[380px] md:min-h-[420px]">
                    <h3 class="text-base font-semibold mb-4 text-slate-600">Performance Radar (First Player in List)</h3>
                    <canvas id="radarChartPlayer"></canvas>
                </div>
            </div>
        </section>
        <?php endif; ?>
         <footer class="text-center mt-12 py-6 border-t border-gray-200">
            <p class="text-xs text-slate-500">© <?= date("Y") ?> NBA Stats Dashboard. All data is for demonstrational purposes.</p>
        </footer>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartDataSource = <?= json_encode($data); ?>;
    if (chartDataSource && chartDataSource.length > 0) {
        const chartData = chartDataSource.slice(0, 15); 

        const playerNamesBar = chartData.map(d => (d.playerName || 'N/A') + ' (' + (d.year || 'N/A') + ')');
        const pointsDataBar = chartData.map(d => d.points || 0);
        const assistsDataBar = chartData.map(d => d.assists || 0);
        const reboundsDataBar = chartData.map(d => d.rebounds || 0);

        const ctxBar = document.getElementById('barChartPlayers')?.getContext('2d');
        if (ctxBar) {
            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: playerNamesBar,
                    datasets: [
                        {
                            label: 'Points',
                            data: pointsDataBar,
                            backgroundColor: 'rgba(79, 70, 229, 0.75)', 
                            borderColor: 'rgba(79, 70, 229, 1)',
                            borderWidth: 1,
                            borderRadius: { topLeft: 5, topRight: 5 },
                            barPercentage: 0.7,
                            categoryPercentage: 0.8,
                        },
                        {
                            label: 'Assists',
                            data: assistsDataBar,
                            backgroundColor: 'rgba(5, 150, 105, 0.75)', 
                            borderColor: 'rgba(5, 150, 105, 1)',
                            borderWidth: 1,
                            borderRadius: { topLeft: 5, topRight: 5 },
                            barPercentage: 0.7,
                            categoryPercentage: 0.8,
                        },
                        {
                            label: 'Rebounds',
                            data: reboundsDataBar,
                            backgroundColor: 'rgba(234, 179, 8, 0.75)', 
                            borderColor: 'rgba(234, 179, 8, 1)',
                            borderWidth: 1,
                            borderRadius: { topLeft: 5, topRight: 5 },
                            barPercentage: 0.7,
                            categoryPercentage: 0.8,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', align: 'center', labels: { usePointStyle: true, boxWidth: 10, padding: 20, font: {size: 11} } },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: '#fff',
                            titleColor: '#1e293b', 
                            bodyColor: '#475569', 
                            borderColor: '#e2e8f0', 
                            borderWidth: 1,
                            padding: 10,
                            cornerRadius: 6,
                            usePointStyle: true,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) { label += ': '; }
                                    if (context.parsed.y !== null) { label += context.parsed.y; }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f1f5f9', drawBorder: false }, 
                            ticks: { color: '#64748b', font: {size: 10} } 
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#64748b', font: {size: 10}, autoSkipPadding: 10 }
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                }
            });
        } else {
            const barChartContainer = document.getElementById('barChartPlayers')?.parentNode;
            if(barChartContainer) barChartContainer.innerHTML = '<div class="flex flex-col items-center justify-center h-full text-slate-500"><i class="fas fa-chart-bar fa-2x mb-3 text-slate-300"></i><p class="text-sm">Bar Chart placeholder: No data or canvas not found.</p></div>';
        }


        const radarCtx = document.getElementById('radarChartPlayer')?.getContext('2d');
        if (radarCtx && chartData.length > 0) {
            const firstPlayerData = chartData[0];
            new Chart(radarCtx, {
                type: 'radar',
                data: {
                    labels: ['Points', 'Assists', 'Rebounds', 'Steals', 'Blocks', 'Turnovers'],
                    datasets: [{
                        label: (firstPlayerData.playerName || 'N/A') + ' (' + (firstPlayerData.year || 'N/A') + ')',
                        data: [
                            firstPlayerData.points || 0,
                            firstPlayerData.assists || 0,
                            firstPlayerData.rebounds || 0,
                            firstPlayerData.steals || 0,
                            firstPlayerData.blocks || 0,
                            firstPlayerData.turnovers || 0
                        ],
                        fill: true,
                        backgroundColor: 'rgba(79, 70, 229, 0.25)',
                        borderColor: 'rgba(79, 70, 229, 0.8)',
                        pointBackgroundColor: 'rgba(79, 70, 229, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(79, 70, 229, 1)',
                        borderWidth: 1.5,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, position: 'bottom', align: 'center', labels: { usePointStyle: true, boxWidth: 10, padding: 15, font: {size: 11} } },
                         tooltip: {
                            backgroundColor: '#fff',
                            titleColor: '#1e293b',
                            bodyColor: '#475569',
                            borderColor: '#e2e8f0',
                            borderWidth: 1,
                            padding: 10,
                            cornerRadius: 6,
                            usePointStyle: true,
                        }
                    },
                    scales: {
                        r: {
                            beginAtZero: true,
                            angleLines: { color: '#e2e8f0' }, 
                            grid: { color: '#f1f5f9' }, 
                            pointLabels: { font: { size: 11, weight: '500' }, color: '#475569' }, 
                            ticks: {
                                backdropColor: 'rgba(255,255,255, 0.85)',
                                color: '#64748b', 
                                font: {size: 9},
                                stepSize: determineRadarStepSizeHelper(firstPlayerData),
                                maxTicksLimit: 6
                            }
                        }
                    },
                    elements: {
                        line: {
                            borderWidth: 2
                        }
                    }
                }
            });
        } else {
            const radarChartContainer = document.getElementById('radarChartPlayer')?.parentNode;
            if(radarChartContainer) radarChartContainer.innerHTML = '<div class="flex flex-col items-center justify-center h-full text-slate-500"><i class="fas fa-compass-drafting fa-2x mb-3 text-slate-300"></i><p class="text-sm">Radar Chart placeholder: No data or canvas not found.</p></div>';
        }
    } else {
        // Handle case where chartDataSource is empty or null
        const barChartContainer = document.getElementById('barChartPlayers')?.parentNode;
        if(barChartContainer) barChartContainer.innerHTML = '<div class="flex flex-col items-center justify-center h-full text-slate-500"><i class="fas fa-chart-bar fa-2x mb-3 text-slate-300"></i><p class="text-sm">No data available for charts.</p></div>';
        
        const radarChartContainer = document.getElementById('radarChartPlayer')?.parentNode;
        if(radarChartContainer) radarChartContainer.innerHTML = '<div class="flex flex-col items-center justify-center h-full text-slate-500"><i class="fas fa-compass-drafting fa-2x mb-3 text-slate-300"></i><p class="text-sm">No data available for charts.</p></div>';
    }


    function determineRadarStepSizeHelper(playerData) {
        if (!playerData) return 5; // Default if no data
        const stats = [
            playerData.points || 0, playerData.assists || 0, playerData.rebounds || 0,
            playerData.steals || 0, playerData.blocks || 0, playerData.turnovers || 0
        ];
        const maxStat = Math.max(...stats.filter(s => typeof s === 'number'));
        if (maxStat === 0) return 1; 
        if (maxStat <= 5) return 1;
        if (maxStat <= 10) return 2;
        if (maxStat <= 20) return 4;
        if (maxStat <= 30) return 5;
        if (maxStat <= 50) return 10;
        return Math.max(1, Math.ceil(maxStat / 5));
    }
});
</script>
</body>
</html>